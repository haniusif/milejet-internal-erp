<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\FinanceInvoice;
use App\Models\FleetVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only CRM / Fleet / Finance feeds for the mobile app, sourced from the
 * Laravel caches. Each is gated by its domain view ability; users without it
 * get an empty list (the module tile is role-gated in the app).
 */
class MobileDomainController extends Controller
{
    public function crmLeads(Request $request): JsonResponse
    {
        if (!$request->user()->can('crm.view')) return response()->json([]);
        $rows = CrmLead::orderByDesc('odoo_create_date')->orderByDesc('id')->limit(100)->get()
            ->map(fn ($l) => [
                'id'         => $l->id,
                'company'    => $l->name ?: ($l->partner_name ?: '—'),
                'contact'    => $l->contact_name ?: ($l->partner_name ?: ''),
                'stage'      => $this->leadStage($l),
                'value'      => (float) $l->expected_revenue,
                'owner'      => $l->salesperson_name ?: '—',
                'updated_at' => ($l->odoo_create_date ?? $l->updated_at)?->toIso8601String(),
            ]);
        return response()->json($rows);
    }

    public function crmCustomers(Request $request): JsonResponse
    {
        if (!$request->user()->can('crm.view')) return response()->json([]);
        $rows = CrmCustomer::where('active', true)->orderBy('name')->limit(200)->get()
            ->map(fn ($c) => [
                'id'                  => $c->id,
                'name'                => $c->name,
                'city'                => $c->city ?: '—',
                'shipments_per_month' => 0, // not tracked per-customer
                'since'               => $c->created_at?->toDateString(),
            ]);
        return response()->json($rows);
    }

    public function fleetVehicles(Request $request): JsonResponse
    {
        if (!$request->user()->can('fleet.view')) return response()->json([]);
        $rows = FleetVehicle::where('active', true)->orderBy('name')->limit(200)->get()
            ->map(fn ($v) => [
                'id'          => $v->id,
                'plate'       => $v->license_plate ?: $v->name,
                'model'       => $v->model_name ?: $v->name,
                'driver'      => $v->driver_name ?: '—',
                'status'      => $this->vehicleStatus($v->state_name),
                'odometer_km' => (int) round((float) $v->odometer),
                'next_service' => null,
                'trips_today' => 0,
            ]);
        return response()->json($rows);
    }

    public function financeInvoices(Request $request): JsonResponse
    {
        if (!$request->user()->can('finance.view')) return response()->json([]);
        $rows = FinanceInvoice::where('move_type', 'out_invoice')
            ->orderByDesc('invoice_date')->orderByDesc('id')->limit(100)->get()
            ->map(fn ($i) => [
                'id'       => $i->id,
                'number'   => $i->name ?: ($i->ref ?: '—'),
                'customer' => $i->partner_name ?: '—',
                'amount'   => (float) $i->amount_total,
                'status'   => $this->invoiceStatus($i),
                'due_date' => $i->invoice_date_due?->toDateString(),
            ]);
        return response()->json($rows);
    }

    public function financeExpenses(Request $request): JsonResponse
    {
        if (!$request->user()->can('finance.view')) return response()->json([]);
        // Vendor bills as outgoing spend (categories aren't tracked in the cache).
        $rows = FinanceInvoice::where('move_type', 'in_invoice')
            ->orderByDesc('invoice_date')->orderByDesc('id')->limit(100)->get()
            ->map(fn ($i) => [
                'id'       => $i->id,
                'category' => 'other',
                'amount'   => (float) $i->amount_total,
                'date'     => $i->invoice_date?->toDateString(),
            ]);
        return response()->json($rows);
    }

    // ── helpers ──

    private function leadStage(CrmLead $l): string
    {
        if (!$l->active) return 'lost';
        if ($l->is_won) return 'won';
        $s = strtolower((string) $l->stage_name);
        if (str_contains($s, 'propos')) return 'proposal';
        if (str_contains($s, 'qualif')) return 'qualified';
        if (str_contains($s, 'won')) return 'won';
        return 'new';
    }

    private function vehicleStatus(?string $state): string
    {
        $s = strtolower((string) $state);
        if (str_contains($s, 'mainten') || str_contains($s, 'repair') || str_contains($s, 'service')) return 'maintenance';
        if (str_contains($s, 'inactive') || str_contains($s, 'down') || str_contains($s, 'idle')) return 'idle';
        return 'active';
    }

    private function invoiceStatus(FinanceInvoice $i): string
    {
        if (in_array($i->payment_state, ['paid', 'in_payment', 'reversed'], true)) return 'paid';
        if ($i->invoice_date_due && $i->invoice_date_due->isPast()) return 'overdue';
        return 'due';
    }
}
