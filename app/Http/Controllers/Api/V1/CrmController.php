<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\CrmStage;
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

    public function lost(int $id): JsonResponse
    {
        $lead = CrmLead::findOrFail($id);

        try {
            $this->odoo->write('crm.lead', [$lead->odoo_id], ['active' => false, 'probability' => 0]);
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
