<?php

namespace App\Http\Controllers;

use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\CrmStage;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use RuntimeException;

class CrmController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    /** Pipeline — the CRM module home. */
    public function index(Request $request)
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

        $leads  = $query->with('stage')->orderByDesc('odoo_create_date')->paginate(20)->withQueryString();
        $stages = CrmStage::orderBy('sequence')->get();

        // Pipeline totals (open deals only)
        $wonStageIds = CrmStage::where('is_won', true)->pluck('odoo_id');
        $stats = [
            'open'             => CrmLead::where('active', true)->whereNotIn('odoo_stage_id', $wonStageIds)->count(),
            'won'              => CrmLead::where('active', true)->whereIn('odoo_stage_id', $wonStageIds)->count(),
            'lost'             => CrmLead::where('active', false)->count(),
            'expected_revenue' => (float) CrmLead::where('active', true)
                                      ->whereNotIn('odoo_stage_id', $wonStageIds)->sum('expected_revenue'),
        ];

        return view('crm.leads', compact('leads', 'stages', 'stats'));
    }

    public function createLead()
    {
        $customers = CrmCustomer::where('active', true)->orderBy('name')->get();

        return view('crm.create_lead', compact('customers'));
    }

    public function storeLead(Request $request)
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
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => __('Could not create the opportunity in Odoo: :error', ['error' => $e->getMessage()])]);
        }

        $this->sync->syncCrm();

        return redirect()->route('crm.index')
            ->with('status', __('✅ Opportunity ":name" created', ['name' => $data['name']]));
    }

    public function moveStage(Request $request, int $id)
    {
        $lead = CrmLead::findOrFail($id);
        $data = $request->validate([
            'stage_id' => 'required|integer|exists:crm_stages,odoo_id',
        ]);

        try {
            $this->odoo->write('crm.lead', [$lead->odoo_id], ['stage_id' => (int) $data['stage_id']]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not update the stage: :error', ['error' => $e->getMessage()])]);
        }

        $stage = CrmStage::where('odoo_id', $data['stage_id'])->first();
        $lead->update([
            'odoo_stage_id' => $stage->odoo_id,
            'stage_name'    => $stage->name,
            'synced_at'     => now(),
        ]);

        return back()->with('status', __('✅ ":name" moved to ":stage"', ['name' => $lead->name, 'stage' => $stage->name]));
    }

    /** Mark won via Odoo's native action (sets the won stage + probability). */
    public function won(int $id)
    {
        $lead = CrmLead::findOrFail($id);

        try {
            $this->odoo->executeKw('crm.lead', 'action_set_won', [[$lead->odoo_id]]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not mark as won: :error', ['error' => $e->getMessage()])]);
        }

        $this->sync->syncCrm();

        return back()->with('status', __('🎉 ":name" marked as won', ['name' => $lead->name]));
    }

    /**
     * Mark lost = archive + zero probability (what action_set_lost does;
     * the action itself returns None, which XML-RPC cannot marshal).
     */
    public function lost(int $id)
    {
        $lead = CrmLead::findOrFail($id);

        try {
            $this->odoo->write('crm.lead', [$lead->odoo_id], ['active' => false, 'probability' => 0]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not mark as lost: :error', ['error' => $e->getMessage()])]);
        }

        $lead->update(['active' => false, 'probability' => 0, 'synced_at' => now()]);

        return back()->with('status', __('":name" marked as lost', ['name' => $lead->name]));
    }

    /** Restore a lost opportunity back into the pipeline. */
    public function restore(int $id)
    {
        $lead = CrmLead::findOrFail($id);

        try {
            $this->odoo->write('crm.lead', [$lead->odoo_id], ['active' => true]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not restore the opportunity: :error', ['error' => $e->getMessage()])]);
        }

        $lead->update(['active' => true, 'synced_at' => now()]);

        return back()->with('status', __('✅ ":name" restored', ['name' => $lead->name]));
    }

    public function customers(Request $request)
    {
        $query = CrmCustomer::query();

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->withCount('leads')->orderBy('name')->paginate(20)->withQueryString();

        return view('crm.customers', compact('customers'));
    }

    public function createCustomer()
    {
        return view('crm.create_customer');
    }

    public function storeCustomer(Request $request)
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
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => __('Could not create the customer in Odoo: :error', ['error' => $e->getMessage()])]);
        }

        $this->sync->syncCrm();

        return redirect()->route('crm.customers')
            ->with('status', __('✅ Customer ":name" created', ['name' => $data['name']]));
    }
}
