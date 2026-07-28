<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FinanceInvoice;
use App\Models\FleetVehicle;
use App\Models\Leave;
use App\Models\Payslip;
use App\Services\OdooService;
use Carbon\Carbon;
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
        $cached = CrmCustomer::where('active', true)->orderBy('name')->limit(200)->get();
        if ($cached->isNotEmpty()) {
            $rows = $cached->map(fn ($c) => [
                'id'                  => $c->id,
                'name'                => $c->name,
                'city'                => $c->city ?: '—',
                'shipments_per_month' => 0, // not tracked per-customer
                'since'               => $c->created_at?->toDateString(),
            ]);
            return response()->json($rows);
        }
        // No partners flagged as customers — fall back to company partners from Odoo.
        try {
            $partners = app(OdooService::class)->searchRead('res.partner',
                [['is_company', '=', true]], ['id', 'name', 'city', 'create_date'], 200, 0, 'name');
        } catch (\Throwable) {
            $partners = [];
        }
        return response()->json(array_map(fn ($p) => [
            'id'                  => $p['id'],
            'name'                => $p['name'] ?: '—',
            'city'                => ($p['city'] ?? false) ?: '—',
            'shipments_per_month' => 0,
            'since'               => !empty($p['create_date']) ? substr($p['create_date'], 0, 10) : null,
        ], $partners));
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

    /** HR dashboard — real stats only (manager view). */
    public function dashboard(Request $request): JsonResponse
    {
        $empty = [
            'total_employees' => 0, 'departments' => 0, 'on_leave_today' => 0,
            'pending_leaves' => 0, 'present_today' => 0, 'attendance_rate' => 0,
            'contracts_expiring' => 0, 'iqamas_expiring' => 0,
            'payroll_month' => 0, 'payroll_month_label' => null,
            'attendance_trend' => [], 'headcount_by_dept' => [],
        ];
        if (!$request->user()->can('hr.view_all')) {
            return response()->json($empty);
        }

        $today = now()->startOfDay();
        $deadline = $today->copy()->addDays(60);
        $active = Employee::where('active', true)->count();
        $present = Attendance::whereDate('check_in', today())->count();
        $lastPay = ($d = Payslip::max('date_from')) ? Carbon::parse($d)->startOfMonth() : $today->copy()->startOfMonth();

        return response()->json([
            'total_employees'  => $active,
            'departments'      => Department::count(),
            'on_leave_today'   => Leave::where('state', 'validate')
                                    ->whereDate('date_from', '<=', $today)
                                    ->whereDate('date_to', '>=', $today)->count(),
            'pending_leaves'   => Leave::where('state', 'confirm')->count(),
            'present_today'    => $present,
            'attendance_rate'  => $active ? (int) round($present / $active * 100) : 0,
            'contracts_expiring' => Contract::where('state', 'open')->whereNotNull('date_end')
                                        ->whereBetween('date_end', [$today, $deadline])->count(),
            'iqamas_expiring'  => Employee::where('active', true)->whereNotNull('iqama_expiry_date')
                                        ->where('iqama_expiry_date', '<=', $deadline)->count(),
            'payroll_month'    => (float) Payslip::whereBetween('date_from',
                                        [$lastPay, $lastPay->copy()->endOfMonth()])->sum('net_total'),
            'payroll_month_label' => $lastPay->format('Y-m'),
            'attendance_trend' => collect(range(6, 0))->map(fn ($i) => [
                'label' => now()->subDays($i)->format('D'),
                'value' => Attendance::whereDate('check_in', now()->subDays($i)->toDateString())->count(),
            ])->values(),
            'headcount_by_dept' => Employee::where('active', true)->whereNotNull('department_name')
                ->selectRaw('department_name AS dept, COUNT(*) AS count')
                ->groupBy('department_name')->orderByDesc('count')->limit(8)->get(),
        ]);
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
