<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\CrmLostReason;
use App\Models\CrmStage;
use App\Models\CrmTag;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CrmController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    /** Pipeline: stages + leads + stats — enough for kanban or list views. */
    public function pipeline(Request $request): JsonResponse
    {
        $query = CrmLead::query();

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('email_from', 'like', "%{$search}%");
            });
        }
        if ($stageId = $request->get('stage_id')) {
            $query->where('odoo_stage_id', $stageId);
        }
        match ($request->get('status')) {
            'lost'  => $query->where('active', false),
            'won'   => $query->where('active', true)
                             ->whereIn('odoo_stage_id', CrmStage::where('is_won', true)->pluck('odoo_id')),
            'all'   => null,
            default => $query->where('active', true),
        };

        $wonStageIds = CrmStage::where('is_won', true)->pluck('odoo_id');

        $page = $query->orderByDesc('odoo_create_date')
            ->paginate(min((int) $request->get('per_page', 20), 200))
            ->withQueryString()->through(fn ($l) => $this->leadSummary($l));

        return response()->json($page->toArray() + [
            'stages' => CrmStage::orderBy('sequence')->get()->map(fn ($s) => [
                'odoo_id'  => $s->odoo_id,
                'name'     => $s->name,
                'sequence' => $s->sequence,
                'is_won'   => (bool) $s->is_won,
            ]),
            'stats' => [
                'open'             => CrmLead::where('active', true)->whereNotIn('odoo_stage_id', $wonStageIds)->count(),
                'won'              => CrmLead::where('active', true)->whereIn('odoo_stage_id', $wonStageIds)->count(),
                'lost'             => CrmLead::where('active', false)->count(),
                'expected_revenue' => (float) CrmLead::where('active', true)
                                          ->whereNotIn('odoo_stage_id', $wonStageIds)->sum('expected_revenue'),
            ],
        ]);
    }

    public function storeLead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'contact_name'     => 'nullable|string|max:255',
            'partner_id'       => 'nullable|integer|exists:crm_customers,odoo_id',
            'email_from'       => 'nullable|email|max:255',
            'phone'            => 'nullable|string|max:64',
            'expected_revenue' => 'nullable|numeric|min:0',
            'date_deadline'    => 'nullable|date',
            'description'      => 'nullable|string|max:5000',
        ]);

        try {
            $this->odoo->create('crm.lead', array_filter([
                'name'             => $data['name'],
                'type'             => 'opportunity',
                'contact_name'     => $data['contact_name'] ?? null,
                'partner_id'       => isset($data['partner_id']) ? (int) $data['partner_id'] : null,
                'email_from'       => $data['email_from'] ?? null,
                'phone'            => $data['phone'] ?? null,
                'expected_revenue' => $data['expected_revenue'] ?? null,
                'date_deadline'    => $data['date_deadline'] ?? null,
                'description'      => $data['description'] ?? null,
            ]));
            $this->sync->syncCrm();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'created'], 201);
    }

    public function moveStage(Request $request, int $id): JsonResponse
    {
        $lead = CrmLead::findOrFail($id);
        $data = $request->validate(['stage_id' => 'required|integer|exists:crm_stages,odoo_id']);

        try {
            $this->odoo->write('crm.lead', [$lead->odoo_id], ['stage_id' => (int) $data['stage_id']]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $stage = CrmStage::where('odoo_id', $data['stage_id'])->first();
        $lead->update(['odoo_stage_id' => $stage->odoo_id, 'stage_name' => $stage->name, 'synced_at' => now()]);

        return response()->json(['data' => $this->leadSummary($lead->fresh())]);
    }

    public function won(int $id): JsonResponse
    {
        $lead = CrmLead::findOrFail($id);

        try {
            $this->odoo->executeKw('crm.lead', 'action_set_won', [[$lead->odoo_id]]);
            $this->sync->syncCrm();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->leadSummary($lead->fresh())]);
    }

    public function lost(Request $request, int $id): JsonResponse
    {
        $lead = CrmLead::findOrFail($id);
        $data = $request->validate(['lost_reason_id' => 'nullable|integer|exists:crm_lost_reasons,odoo_id']);

        $payload = ['active' => false, 'probability' => 0];
        if (!empty($data['lost_reason_id'])) {
            $payload['lost_reason_id'] = (int) $data['lost_reason_id'];
        }

        try {
            $this->odoo->write('crm.lead', [$lead->odoo_id], $payload);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $lead->update(['active' => false, 'probability' => 0, 'synced_at' => now()]);

        return response()->json(['data' => $this->leadSummary($lead->fresh())]);
    }

    public function restore(int $id): JsonResponse
    {
        $lead = CrmLead::findOrFail($id);

        try {
            $this->odoo->write('crm.lead', [$lead->odoo_id], ['active' => true]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $lead->update(['active' => true, 'synced_at' => now()]);

        return response()->json(['data' => $this->leadSummary($lead->fresh())]);
    }

    // ─── mj_crm_core: detail / edit / activities / 360° ──────────────

    /** Full lead detail: summary + description/tags + live activities + notes + mini-360. */
    public function leadDetail(int $id): JsonResponse
    {
        $lead = CrmLead::findOrFail($id);

        $activities = $this->activitiesFor('crm.lead', $lead->odoo_id);
        $notes = $this->notesFor('crm.lead', $lead->odoo_id);

        $customer = null;
        if ($lead->odoo_partner_id && ($c = CrmCustomer::where('odoo_id', $lead->odoo_partner_id)->first())) {
            $customer = [
                'id'              => $c->id,
                'name'            => $c->name,
                'account_manager' => $c->account_manager,
                'credit_limit'    => (float) $c->credit_limit,
                'leads_count'     => $c->leads()->count(),
            ];
        }

        return response()->json(['data' => $this->leadSummary($lead) + [
            'description' => $lead->description,
            'tag_names'   => $lead->tag_names,
            'team_name'   => $lead->team_name,
            'lost_reason' => $lead->lost_reason,
            'activities'  => $activities,
            'notes'       => $notes,
            'customer'    => $customer,
        ]]);
    }

    public function updateLead(Request $request, int $id): JsonResponse
    {
        $lead = CrmLead::findOrFail($id);
        $data = $request->validate([
            'name'             => 'sometimes|required|string|max:255',
            'contact_name'     => 'nullable|string|max:255',
            'email_from'       => 'nullable|email|max:255',
            'phone'            => 'nullable|string|max:64',
            'mobile'           => 'nullable|string|max:64',
            'expected_revenue' => 'nullable|numeric|min:0',
            'probability'      => 'nullable|numeric|min:0|max:100',
            'user_id'          => 'nullable|integer', // salesperson res.users id
            'date_deadline'    => 'nullable|date',
            'priority'         => 'nullable|in:0,1,2,3',
            'description'      => 'nullable|string|max:10000',
            'tag_ids'          => 'nullable|array',
            'tag_ids.*'        => 'integer|exists:crm_tags,odoo_id',
        ]);

        $payload = [];
        foreach (['name', 'contact_name', 'email_from', 'phone', 'mobile', 'description'] as $f) {
            if (array_key_exists($f, $data)) $payload[$f] = $data[$f] ?: false;
        }
        foreach (['expected_revenue', 'probability'] as $f) {
            if (array_key_exists($f, $data)) $payload[$f] = $data[$f] === null ? 0 : (float) $data[$f];
        }
        if (array_key_exists('date_deadline', $data)) $payload['date_deadline'] = $data['date_deadline'] ?: false;
        if (array_key_exists('priority', $data)) $payload['priority'] = (string) $data['priority'];
        if (array_key_exists('user_id', $data)) $payload['user_id'] = $data['user_id'] ? (int) $data['user_id'] : false;
        if (array_key_exists('tag_ids', $data)) {
            $payload['tag_ids'] = [[6, 0, array_map('intval', $data['tag_ids'] ?? [])]];
        }

        try {
            $this->odoo->write('crm.lead', [$lead->odoo_id], $payload);
            $this->sync->syncCrm();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->leadSummary($lead->fresh())]);
    }

    /** Pickers for the edit form: salespeople, tags, lost reasons, stages. */
    public function config(): JsonResponse
    {
        $salespeople = cache()->remember('crm.salespeople', now()->addHour(), function () {
            return $this->odoo->searchRead('res.users', [['share', '=', false]], ['id', 'name'], 0, 0, 'name asc');
        });

        return response()->json(['data' => [
            'salespeople'  => $salespeople,
            'tags'         => CrmTag::orderBy('name')->get(['odoo_id', 'name', 'color']),
            'lost_reasons' => CrmLostReason::orderBy('name')->get(['odoo_id', 'name']),
            'stages'       => CrmStage::orderBy('sequence')->get(['odoo_id', 'name', 'is_won']),
        ]]);
    }

    public function storeActivity(Request $request, int $id): JsonResponse
    {
        $lead = CrmLead::findOrFail($id);
        $data = $request->validate([
            'type'    => 'required|in:call,meeting,todo,email',
            'summary' => 'nullable|string|max:255',
            'note'    => 'nullable|string|max:2000',
            'date'    => 'nullable|date',
        ]);

        try {
            $modelId = $this->odoo->searchRead('ir.model', [['model', '=', 'crm.lead']], ['id'], 1)[0]['id'] ?? null;
            $typeName = ['call' => 'mail_activity_data_call', 'meeting' => 'mail_activity_data_meeting',
                         'todo' => 'mail_activity_data_todo', 'email' => 'mail_activity_data_email'][$data['type']];
            // Odoo 17: resolve the activity-type xmlid → res_id via check_object_reference.
            $ref = $this->odoo->executeKw('ir.model.data', 'check_object_reference', ['mail', $typeName]);
            $typeId = is_array($ref) ? ($ref[1] ?? false) : false;
            $this->odoo->create('mail.activity', array_filter([
                'res_model_id'   => $modelId,
                'res_id'         => $lead->odoo_id,
                'activity_type_id' => $typeId ?: false,
                'summary'        => $data['summary'] ?? false,
                'note'           => $data['note'] ?? false,
                'date_deadline'  => $data['date'] ?? now()->toDateString(),
            ], fn ($v) => $v !== false && $v !== null));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['activities' => $this->activitiesFor('crm.lead', $lead->odoo_id)]], 201);
    }

    public function doneActivity(Request $request, int $activityOdooId): JsonResponse
    {
        $data = $request->validate(['feedback' => 'nullable|string|max:2000']);
        try {
            $this->odoo->executeKw('mail.activity', 'action_feedback', [[$activityOdooId]],
                ['feedback' => $data['feedback'] ?? false]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['message' => 'done']);
    }

    public function storeNote(Request $request, int $id): JsonResponse
    {
        $lead = CrmLead::findOrFail($id);
        $data = $request->validate(['note' => 'required|string|max:5000']);
        try {
            $this->odoo->executeKw('crm.lead', 'message_post', [[$lead->odoo_id]],
                ['body' => e($data['note']), 'message_type' => 'comment', 'subtype_xmlid' => 'mail.mt_comment']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['notes' => $this->notesFor('crm.lead', $lead->odoo_id)]], 201);
    }

    public function updateCustomer(Request $request, int $id): JsonResponse
    {
        $customer = CrmCustomer::findOrFail($id);
        $data = $request->validate([
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:64',
            'mobile'       => 'nullable|string|max:64',
            'city'         => 'nullable|string|max:128',
            'vat'          => 'nullable|string|max:64',
            'credit_limit' => 'nullable|numeric|min:0',
            'user_id'      => 'nullable|integer', // account manager
        ]);

        $payload = [];
        foreach (['email', 'phone', 'mobile', 'city', 'vat'] as $f) {
            if (array_key_exists($f, $data)) $payload[$f] = $data[$f] ?: false;
        }
        if (array_key_exists('credit_limit', $data)) $payload['credit_limit'] = (float) ($data['credit_limit'] ?? 0);
        if (array_key_exists('user_id', $data)) $payload['user_id'] = $data['user_id'] ? (int) $data['user_id'] : false;

        try {
            $this->odoo->write('res.partner', [$customer->odoo_id], $payload);
            $this->sync->syncCrm();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $customer->id]]);
    }

    /** Customer 360°: profile + opps + financials (invoiced/due/payments) + activities. */
    public function customer360(int $id): JsonResponse
    {
        $customer = CrmCustomer::findOrFail($id);
        $pid = $customer->odoo_id;

        // Opportunities (from local cache)
        $opps = CrmLead::where('odoo_partner_id', $pid)->orderByDesc('odoo_create_date')->get()
            ->map(fn ($l) => $this->leadSummary($l));

        // Financials — live read from Odoo accounting.
        $invoiced = 0.0; $due = 0.0; $invoices = []; $payments = [];
        try {
            $moves = $this->odoo->searchRead('account.move',
                [['partner_id', '=', $pid], ['move_type', '=', 'out_invoice'], ['state', '=', 'posted']],
                ['id', 'name', 'invoice_date', 'amount_total', 'amount_residual', 'payment_state'], 20, 0, 'invoice_date desc');
            foreach ($moves as $m) {
                $invoiced += (float) $m['amount_total'];
                $due += (float) $m['amount_residual'];
                $invoices[] = ['name' => $m['name'], 'date' => $m['invoice_date'] ?: null,
                    'total' => (float) $m['amount_total'], 'residual' => (float) $m['amount_residual'],
                    'payment_state' => $m['payment_state'] ?? null];
            }
            $pays = $this->odoo->searchRead('account.payment',
                [['partner_id', '=', $pid], ['state', '=', 'posted']],
                ['id', 'name', 'date', 'amount'], 20, 0, 'date desc');
            foreach ($pays as $p) $payments[] = ['name' => $p['name'], 'date' => $p['date'] ?: null, 'amount' => (float) $p['amount']];
        } catch (\Throwable) {
            // accounting may be sparse; degrade gracefully
        }

        return response()->json(['data' => [
            'id'              => $customer->id,
            'odoo_id'         => $customer->odoo_id,
            'name'            => $customer->name,
            'is_company'      => (bool) $customer->is_company,
            'email'           => $customer->email,
            'phone'           => $customer->phone,
            'mobile'          => $customer->mobile,
            'city'            => $customer->city,
            'country'         => $customer->country_name,
            'vat'             => $customer->vat,
            'account_manager' => $customer->account_manager,
            'credit_limit'    => (float) $customer->credit_limit,
            'kpis' => [
                'opps'      => $opps->count(),
                'invoiced'  => round($invoiced, 2),
                'due'       => round($due, 2),
                'shipments' => null, // hook — milejet_shipment (P0) not yet built
            ],
            'opportunities' => $opps,
            'invoices'      => $invoices,
            'payments'      => $payments,
            'activities'    => $this->activitiesFor('res.partner', $pid),
        ]]);
    }

    /** Live activities for a record (open + recent). */
    protected function activitiesFor(string $model, int $resId): array
    {
        try {
            $rows = $this->odoo->searchRead('mail.activity',
                [['res_model', '=', $model], ['res_id', '=', $resId]],
                ['id', 'summary', 'activity_type_id', 'date_deadline', 'user_id', 'state', 'note'], 30, 0, 'date_deadline asc');
        } catch (\Throwable) {
            return [];
        }
        return array_map(fn ($a) => [
            'odoo_id'  => $a['id'],
            'type'     => OdooService::many2oneName($a['activity_type_id']),
            'summary'  => $a['summary'] ?: null,
            'note'     => is_string($a['note']) ? strip_tags($a['note']) : null,
            'deadline' => $a['date_deadline'] ?: null,
            'user'     => OdooService::many2oneName($a['user_id']),
            'state'    => $a['state'] ?? null, // overdue / today / planned
        ], $rows);
    }

    /** Recent chatter notes (message_type comment). */
    protected function notesFor(string $model, int $resId): array
    {
        try {
            $rows = $this->odoo->searchRead('mail.message',
                [['model', '=', $model], ['res_id', '=', $resId], ['message_type', '=', 'comment']],
                ['id', 'body', 'author_id', 'date'], 20, 0, 'date desc');
        } catch (\Throwable) {
            return [];
        }
        return array_map(fn ($m) => [
            'author' => OdooService::many2oneName($m['author_id']),
            'date'   => $m['date'] ?: null,
            'body'   => trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", (string) $m['body']))),
        ], $rows);
    }

    public function customers(Request $request): JsonResponse
    {
        $query = CrmCustomer::query();

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->withCount('leads')->orderBy('name')
                ->paginate(min((int) $request->get('per_page', 20), 100))
                ->withQueryString()->through(fn ($c) => [
                    'id'          => $c->id,
                    'odoo_id'     => $c->odoo_id,
                    'name'        => $c->name,
                    'is_company'  => (bool) $c->is_company,
                    'email'       => $c->email,
                    'phone'       => $c->phone,
                    'city'        => $c->city,
                    'country'     => $c->country_name,
                    'vat'         => $c->vat,
                    'leads_count' => $c->leads_count,
                ])
        );
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'is_company' => 'nullable|boolean',
            'email'      => 'nullable|email|max:255',
            'phone'      => 'nullable|string|max:64',
            'city'       => 'nullable|string|max:128',
            'vat'        => 'nullable|string|max:64',
        ]);

        try {
            $this->odoo->create('res.partner', array_filter([
                'name'          => $data['name'],
                'is_company'    => (bool) ($data['is_company'] ?? false),
                'email'         => $data['email'] ?? null,
                'phone'         => $data['phone'] ?? null,
                'city'          => $data['city'] ?? null,
                'vat'           => $data['vat'] ?? null,
                'customer_rank' => 1,
            ]));
            $this->sync->syncCrm();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'created'], 201);
    }

    protected function leadSummary(CrmLead $l): array
    {
        return [
            'id'               => $l->id,
            'odoo_id'          => $l->odoo_id,
            'name'             => $l->name,
            'contact_name'     => $l->contact_name,
            'partner_name'     => $l->partner_name,
            'email_from'       => $l->email_from,
            'phone'            => $l->phone,
            'expected_revenue' => $l->expected_revenue,
            'probability'      => $l->probability,
            'stage_id'         => $l->odoo_stage_id,
            'stage_name'       => $l->stage_name,
            'salesperson'      => $l->salesperson_name,
            'date_deadline'    => $l->date_deadline,
            'priority'         => $l->priority,
            'active'           => (bool) $l->active,
            'created_at'       => $l->odoo_create_date,
        ];
    }
}
