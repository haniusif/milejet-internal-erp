<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmContract;
use App\Models\CrmLead;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Customer service contracts (Odoo mj.crm.contract via mj_crm_contract).
 * Sales (crm.view/write) manage contracts + rate cards; Finance (finance.view)
 * generates the recurring customer invoices (account.move), which surface in the
 * existing Finance module.
 */
class CrmContractController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.view'), 403);
        $q = CrmContract::query();
        if ($state = $request->get('state')) $q->where('state', $state);
        if ($partnerId = $request->get('partner_id')) $q->where('odoo_partner_id', (int) $partnerId);
        $page = $q->orderByDesc('date_start')->orderByDesc('id')
            ->paginate(min((int) $request->get('per_page', 25), 100))
            ->withQueryString()->through(fn ($c) => $this->summary($c));
        return response()->json($page->toArray());
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('crm.view'), 403);
        $c = CrmContract::with('lines')->findOrFail($id);
        $data = $this->summary($c) + [
            'odoo_lead_id' => $c->odoo_lead_id, 'payment_term' => $c->payment_term,
            'delivery_sla_hours' => $c->delivery_sla_hours, 'on_time_target' => $c->on_time_target,
            'lines' => $c->lines->sortBy('sequence')->values()->map(fn ($l) => [
                'id' => $l->id, 'odoo_id' => $l->odoo_id, 'odoo_product_id' => $l->odoo_product_id,
                'product_name' => $l->product_name, 'name' => $l->name, 'basis' => $l->basis,
                'quantity' => $l->quantity, 'price_unit' => $l->price_unit, 'price_subtotal' => $l->price_subtotal,
            ]),
            'invoices' => $this->contractInvoices($c->odoo_id),
        ];
        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.write'), 403);
        $data = $this->validatePayload($request, true);
        try {
            $odooId = $this->odoo->create('mj.crm.contract', $this->buildPayload($data, true));
            $c = $this->sync->refreshCrmContract($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['id' => $c?->id, 'odoo_id' => $odooId]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('crm.write'), 403);
        $c = CrmContract::findOrFail($id);
        if ($c->state !== 'draft') {
            return response()->json(['message' => __('Only a draft contract can be edited.')], 422);
        }
        $data = $this->validatePayload($request, false);
        try {
            // replace rate card if provided
            $payload = $this->buildPayload($data, false);
            if (isset($data['lines'])) {
                $cmds = [[5, 0, 0]];
                foreach ($data['lines'] as $l) {
                    $cmds[] = [0, 0, array_filter([
                        'product_id' => (int) $l['product_id'],
                        'name' => $l['name'] ?? null, 'basis' => $l['basis'] ?? 'fixed',
                        'quantity' => $l['quantity'] ?? 1, 'price_unit' => $l['price_unit'] ?? 0,
                    ], fn ($v) => $v !== null)];
                }
                $payload['line_ids'] = $cmds;
            }
            if ($payload) $this->odoo->write('mj.crm.contract', [$c->odoo_id], $payload);
            $this->sync->refreshCrmContract($c->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return $this->show($request, $id);
    }

    public function action(Request $request, int $id, string $action): JsonResponse
    {
        abort_unless($request->user()->can('crm.write'), 403);
        $map = ['confirm' => 'action_confirm', 'renew' => 'action_renew',
                'close' => 'action_close', 'cancel' => 'action_cancel', 'reset' => 'action_reset'];
        abort_unless(isset($map[$action]), 404);
        $c = CrmContract::findOrFail($id);
        try {
            $this->odoo->executeKw('mj.crm.contract', $map[$action], [[$c->odoo_id]]);
            $this->sync->refreshCrmContract($c->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return $this->show($request, $id);
    }

    public function invoice(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('finance.view') || $request->user()->can('crm.write'), 403);
        $c = CrmContract::findOrFail($id);
        try {
            $this->odoo->useServiceAccount()->executeKw('mj.crm.contract', 'action_generate_invoice', [[$c->odoo_id]]);
            $this->sync->refreshCrmContract($c->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return $this->show($request, $id);
    }

    public function fromLead(Request $request, int $leadId): JsonResponse
    {
        abort_unless($request->user()->can('crm.write'), 403);
        $lead = CrmLead::findOrFail($leadId);
        if (!$lead->odoo_partner_id) {
            return response()->json(['message' => __('This opportunity has no linked customer.')], 422);
        }
        try {
            $odooId = $this->odoo->create('mj.crm.contract', array_filter([
                'partner_id' => $lead->odoo_partner_id,
                'crm_lead_id' => $lead->odoo_id,
            ]));
            $c = $this->sync->refreshCrmContract($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['id' => $c?->id, 'odoo_id' => $odooId]], 201);
    }

    /** Service catalog for the rate-card picker. */
    public function products(): JsonResponse
    {
        $rows = cache()->remember('crm.contract.products', now()->addMinutes(30), function () {
            try {
                return $this->odoo->useServiceAccount()->searchRead('product.product',
                    [['sale_ok', '=', true]], ['id', 'display_name', 'list_price'], 500, 0, 'name');
            } catch (\Throwable) {
                return [];
            }
        });
        return response()->json(['data' => array_map(fn ($p) => [
            'id' => $p['id'], 'name' => $p['display_name'], 'price' => (float) ($p['list_price'] ?? 0),
        ], $rows)]);
    }

    // --- helpers ---

    protected function contractInvoices(int $contractOdooId): array
    {
        try {
            $rows = $this->odoo->useServiceAccount()->searchRead('account.move',
                [['mj_contract_id', '=', $contractOdooId]],
                ['id', 'name', 'invoice_date', 'amount_total', 'state', 'payment_state'], 100, 0, 'invoice_date desc');
        } catch (\Throwable) {
            $rows = [];
        }
        return array_map(fn ($m) => [
            'name' => $m['name'] !== '/' ? $m['name'] : __('Draft'),
            'invoice_date' => $m['invoice_date'] ?: null,
            'amount_total' => (float) ($m['amount_total'] ?? 0),
            'state' => $m['state'] ?? null, 'payment_state' => $m['payment_state'] ?? null,
        ], $rows);
    }

    protected function validatePayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'partner_id' => ($creating ? 'required' : 'nullable') . '|integer',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'recurrence' => ['nullable', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'auto_renew' => 'boolean',
            'delivery_sla_hours' => 'nullable|integer|min:0',
            'on_time_target' => 'nullable|numeric|min:0|max:100',
            'lines' => 'nullable|array',
            'lines.*.product_id' => 'required_with:lines|integer',
            'lines.*.name' => 'nullable|string|max:255',
            'lines.*.basis' => ['nullable', Rule::in(['fixed', 'per_unit'])],
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.price_unit' => 'nullable|numeric|min:0',
        ]);
    }

    protected function buildPayload(array $data, bool $creating): array
    {
        $payload = array_filter([
            'partner_id' => isset($data['partner_id']) ? (int) $data['partner_id'] : null,
            'date_start' => $data['date_start'] ?? null, 'date_end' => $data['date_end'] ?? null,
            'recurrence' => $data['recurrence'] ?? null,
            'delivery_sla_hours' => $data['delivery_sla_hours'] ?? null,
            'on_time_target' => $data['on_time_target'] ?? null,
        ], fn ($v) => $v !== null);
        if (array_key_exists('auto_renew', $data)) $payload['auto_renew'] = (bool) $data['auto_renew'];
        if ($creating && !empty($data['lines'])) {
            $payload['line_ids'] = array_map(fn ($l) => [0, 0, array_filter([
                'product_id' => (int) $l['product_id'], 'name' => $l['name'] ?? null,
                'basis' => $l['basis'] ?? 'fixed', 'quantity' => $l['quantity'] ?? 1,
                'price_unit' => $l['price_unit'] ?? 0,
            ], fn ($v) => $v !== null)], $data['lines']);
        }
        return $payload;
    }

    protected function summary(CrmContract $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->name, 'partner_name' => $c->partner_name,
            'odoo_partner_id' => $c->odoo_partner_id, 'user_name' => $c->user_name,
            'date_start' => $c->date_start?->toDateString(), 'date_end' => $c->date_end?->toDateString(),
            'recurrence' => $c->recurrence, 'auto_renew' => $c->auto_renew,
            'currency' => $c->currency, 'amount_recurring' => $c->amount_recurring,
            'next_invoice_date' => $c->next_invoice_date?->toDateString(),
            'state' => $c->state, 'invoice_count' => $c->invoice_count,
        ];
    }
}
