<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LeaveController as WebLeaveController;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Services\ExcelExport;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * HR module API. Mirrors the web controllers' role scoping exactly:
 * staff (hr.view_all) see everything; plain employees only their own
 * profile, leaves, attendance and payslips.
 */
class HrController extends Controller
{
    /** Days ahead a document expiry (iqama/license/passport) counts as an alert. */
    private const DOC_EXPIRY_WINDOW_DAYS = 60;

    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    // ─── Dashboard ───────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('hr.view_all')) {
            $own = $user->employeeRecord();
            $ownId = $own?->odoo_id ?? -1;

            return response()->json([
                'scope' => 'self',
                'stats' => [
                    'pending_leaves'  => Leave::where('odoo_employee_id', $ownId)->where('state', 'confirm')->count(),
                    'approved_leaves' => Leave::where('odoo_employee_id', $ownId)->where('state', 'validate')->count(),
                    'checked_in'      => Attendance::where('odoo_employee_id', $ownId)
                                            ->whereDate('check_in', today())->whereNull('check_out')->exists(),
                ],
                'recent_leaves' => Leave::where('odoo_employee_id', $ownId)
                    ->orderByDesc('odoo_id')->limit(5)->get()->map(fn ($l) => $this->leaveSummary($l)),
            ]);
        }

        $today    = now()->startOfDay();
        $deadline = $today->copy()->addDays(self::DOC_EXPIRY_WINDOW_DAYS);
        $docExpiry = fn (string $col) => Employee::where('active', true)
            ->whereNotNull($col)->where($col, '<=', $deadline)->count();

        // Latest month that has payslips (payroll usually lags the calendar month).
        $lastPay = ($d = Payslip::max('date_from')) ? \Carbon\Carbon::parse($d)->startOfMonth() : $today->copy()->startOfMonth();

        // Last-6-months buckets, oldest first.
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $monthly = function (string $table, string $dateCol, ?string $sumCol = null) use ($months) {
            $rows = \DB::table($table)
                ->selectRaw("DATE_FORMAT($dateCol, '%Y-%m') AS ym, " . ($sumCol ? "SUM($sumCol)" : 'COUNT(*)') . ' AS v')
                ->where($dateCol, '>=', now()->subMonths(5)->startOfMonth())
                ->groupBy('ym')->pluck('v', 'ym');
            return $months->map(fn ($m) => ['label' => $m, 'value' => round((float) ($rows[$m] ?? 0), 2)])->values();
        };

        return response()->json([
            'scope' => 'company',
            'stats' => [
                'employees'        => Employee::where('active', true)->count(),
                'departments'      => Department::count(),
                'pending_leaves'   => Leave::where('state', 'confirm')->count(),
                'approved_leaves'  => Leave::where('state', 'validate')->count(),
                'today_attendance' => Attendance::whereDate('check_in', today())->count(),
            ],
            'kpis' => [
                'payroll_month'       => (float) Payslip::whereBetween('date_from',
                                             [$lastPay, $lastPay->copy()->endOfMonth()])->sum('net_total'),
                'payroll_month_label' => $lastPay->format('Y-m'),
                'contracts_expiring' => Contract::where('state', 'open')->whereNotNull('date_end')
                                            ->whereBetween('date_end', [$today, $deadline])->count(),
                'docs_expiring'      => $docExpiry('iqama_expiry_date')
                                        + $docExpiry('license_expiry_date')
                                        + $docExpiry('passport_expiry_date'),
                'on_leave_today'     => Leave::where('state', 'validate')
                                            ->whereDate('date_from', '<=', $today)
                                            ->whereDate('date_to', '>=', $today)->count(),
            ],
            'charts' => [
                'by_department' => Employee::where('active', true)->whereNotNull('department_name')
                    ->selectRaw('department_name AS label, COUNT(*) AS value')
                    ->groupBy('department_name')->orderByDesc('value')->limit(8)->get(),
                'by_nationality' => Employee::where('active', true)->whereNotNull('nationality')
                    ->selectRaw('nationality AS label, COUNT(*) AS value')
                    ->groupBy('nationality')->orderByDesc('value')->limit(6)->get(),
                'attendance_week' => collect(range(6, 0))->map(fn ($i) => [
                    'label' => now()->subDays($i)->format('D'),
                    'value' => Attendance::whereDate('check_in', now()->subDays($i)->toDateString())->count(),
                ])->values(),
                'leaves_by_month'  => $monthly('leaves', 'date_from'),
                'payroll_by_month' => $monthly('payslips', 'date_from', 'net_total'),
            ],
            'recent_leaves' => Leave::where('state', 'confirm')
                ->latest()->limit(5)->get()->map(fn ($l) => $this->leaveSummary($l)),
        ]);
    }

    // ─── Employees ───────────────────────────────────────────

    public function employees(Request $request): JsonResponse
    {
        $user = $request->user();

        // Plain employees get a single-item list: themselves.
        if (!$user->can('hr.view_all')) {
            $own = $user->employeeRecord();

            return response()->json([
                'data'  => $own ? [$this->employeeSummary($own)] : [],
                'total' => $own ? 1 : 0,
            ]);
        }

        // Document-expiry alert window: already expired or expiring soon.
        $deadline = now()->startOfDay()->addDays(self::DOC_EXPIRY_WINDOW_DAYS);
        $expiry = fn ($q, string $col) => $q->where('active', true)
            ->whereNotNull($col)->where($col, '<=', $deadline);

        $page = $this->employeesQuery($request)->paginate(min((int) $request->get('per_page', 20), 100))
            ->withQueryString()->through(fn ($e) => $this->employeeSummary($e));

        return response()->json($page->toArray() + [
            'totals' => [
                'active'          => Employee::where('active', true)->count(),
                'suspended'       => Employee::where('active', false)->count(),
                'iqama_expiry'    => $expiry(Employee::query(), 'iqama_expiry_date')->count(),
                'license_expiry'  => $expiry(Employee::query(), 'license_expiry_date')->count(),
                'passport_expiry' => $expiry(Employee::query(), 'passport_expiry_date')->count(),
            ],
        ]);
    }

    /** Directory query with the list endpoint's search/department/filter params applied. */
    private function employeesQuery(Request $request)
    {
        $query = Employee::query();

        $deadline = now()->startOfDay()->addDays(self::DOC_EXPIRY_WINDOW_DAYS);
        $expiry = fn ($q, string $col) => $q->where('active', true)
            ->whereNotNull($col)->where($col, '<=', $deadline);

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('work_email', 'like', "%{$search}%")
                  ->orWhere('job_title', 'like', "%{$search}%")
                  ->orWhere('emp_code', 'like', "%{$search}%")
                  ->orWhere('iqama_id', 'like', "%{$search}%")
                  ->orWhere('nationality', 'like', "%{$search}%");
            });
        }
        if ($deptId = $request->get('department_id')) {
            $query->where('odoo_department_id', $deptId);
        }

        $filter = (string) $request->get('filter', '');
        match ($filter) {
            'suspended'       => $query->where('active', false),
            'iqama_expiry'    => $expiry($query, 'iqama_expiry_date')->orderBy('iqama_expiry_date'),
            'license_expiry'  => $expiry($query, 'license_expiry_date')->orderBy('license_expiry_date'),
            'passport_expiry' => $expiry($query, 'passport_expiry_date')->orderBy('passport_expiry_date'),
            default           => $request->boolean('include_inactive') ? null : $query->where('active', true),
        };

        return $query->orderBy('name');
    }

    /** Same dataset as the employees list (current filters applied), as an .xlsx download. */
    public function exportEmployees(Request $request)
    {
        $this->applyLocale($request);

        // ID/compensation columns follow the same gate as the profile page.
        $sensitive = $request->user()->can('employees.view_sensitive');

        $headers = [
            __('Code'), __('Name'), __('Job title'), __('Department'), __('Manager'),
            __('Work email'), __('Mobile'), __('Contract status'),
            __('Iqama expiry'), __('License expiry'), __('Passport expiry'), __('Status'),
        ];
        if ($sensitive) {
            array_push($headers, __('Nationality'), __('Iqama / National ID'), __('Date of joining'), __('Salary'));
        }

        $rows = $this->employeesQuery($request)->get()->map(function ($e) use ($sensitive) {
            $row = [
                $e->emp_code,
                $e->name,
                $e->job_title,
                $e->department_name,
                $e->parent_name,
                $e->work_email,
                $e->mobile_phone,
                $e->contract_status ? __($e->contract_status) : null,
                $e->iqama_expiry_date?->format('Y-m-d'),
                $e->license_expiry_date?->format('Y-m-d'),
                $e->passport_expiry_date?->format('Y-m-d'),
                $e->active ? __('Active') : __('Inactive'),
            ];
            if ($sensitive) {
                array_push($row, $e->nationality, $e->iqama_id, $e->date_of_joining?->format('Y-m-d'), $e->total_salary);
            }
            return $row;
        });

        return ExcelExport::download('employees-' . now()->format('Y-m-d') . '.xlsx', $headers, $rows);
    }

    public function employee(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $user = $request->user();

        $isSelf = $user->employeeRecord()?->id === $employee->id;
        if (!$isSelf && !$user->can('hr.view_all')) {
            return response()->json(['message' => __('You can only view your own profile.')], 403);
        }

        $payload = $this->employeeSummary($employee);

        // Sensitive block: self or the dedicated gate, same as the web profile.
        if ($isSelf || $user->can('employees.view_sensitive')) {
            $payload['sensitive'] = [
                'nationality'       => $employee->nationality,
                'nationality_code'  => $employee->nationality_code,
                'iqama_id'          => $employee->iqama_id,
                'iqama_expiry_date' => $employee->iqama_expiry_date?->toDateString(),
                'passport_id'       => $employee->passport_id,
                'passport_expiry_date' => $employee->passport_expiry_date?->toDateString(),
                'license_expiry_date'  => $employee->license_expiry_date?->toDateString(),
                'birthday'          => $employee->birthday?->toDateString(),
                'family_status'     => $employee->family_status,
                'region'            => $employee->region,
                'cchi_card_type'    => $employee->cchi_card_type,
                'date_of_joining'   => $employee->date_of_joining?->toDateString(),
                'contract_type'     => $employee->contract_type,
                'contract_end_date' => $employee->contract_end_date?->toDateString(),
                'contract_duration_months' => $employee->contract_duration_months,
                'work_schedule'            => $employee->work_schedule,
                'notice_period_days'       => $employee->notice_period_days,
                'probation_period_days'    => $employee->probation_period_days,
                'auto_renewal'             => $employee->auto_renewal,
                'total_salary'      => $employee->total_salary,
                'basic_salary'      => $employee->basic_salary,
                'allowance_house'     => $employee->allowance_house,
                'allowance_rent'      => $employee->allowance_rent,
                'allowance_transport' => $employee->allowance_transport,
                'allowance_car'       => $employee->allowance_car,
                'allowance_special'   => $employee->allowance_special,
                'allowance_project'   => $employee->allowance_project,
                'allowance_food'      => $employee->allowance_food,
                'allowance_other'     => $employee->allowance_other,
                'ot_allowance'        => $employee->ot_allowance,
                'gosi_pm'             => $employee->gosi_pm,
            ];

            $contract = Contract::where('odoo_employee_id', $employee->odoo_id)
                ->orderByRaw("CASE state WHEN 'open' THEN 0 WHEN 'pending' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
                ->latest('odoo_id')->first();
            $payload['contract'] = $contract ? [
                'id'         => $contract->id,
                'name'       => $contract->name,
                'wage'       => $contract->wage,
                'date_start' => $contract->date_start?->toDateString(),
                'date_end'   => $contract->date_end?->toDateString(),
                'state'      => $contract->state,
                'signed'     => (bool) $contract->signed,
            ] : null;
        }

        $payload['recent_leaves'] = Leave::where('odoo_employee_id', $employee->odoo_id)
            ->orderByDesc('date_from')->limit(5)->get()->map(fn ($l) => $this->leaveSummary($l));
        $payload['recent_attendances'] = Attendance::where('odoo_employee_id', $employee->odoo_id)
            ->orderByDesc('check_in')->limit(5)->get()->map(fn ($a) => $this->attendanceSummary($a));

        if ($isSelf || $user->can('payslips.view')) {
            $payload['recent_payslips'] = Payslip::where('odoo_employee_id', $employee->odoo_id)
                ->orderByDesc('date_from')->limit(5)->get()->map(fn ($p) => $this->payslipSummary($p));
        }

        // Org context
        $manager = $employee->odoo_parent_id
            ? Employee::where('odoo_id', $employee->odoo_parent_id)->first()
            : null;
        $payload['manager_employee'] = $manager ? $this->employeeSummary($manager) : null;
        $payload['reports'] = $employee->subordinates()->where('active', true)->orderBy('name')
            ->get()->map(fn ($e) => $this->employeeSummary($e));

        return response()->json(['data' => $payload]);
    }

    public function departments(): JsonResponse
    {
        return response()->json([
            'data' => Department::orderBy('name')->get()
                ->map(fn ($d) => ['odoo_id' => $d->odoo_id, 'name' => $d->name]),
        ]);
    }

    /** Probation periods and contracts ending within the next 10 days (hr.view_all). */
    public function alerts(): JsonResponse
    {
        $today = now()->startOfDay();
        $window = 10;

        $probation = Employee::where('active', true)
            ->whereNotNull('date_of_joining')
            ->where('probation_period_days', '>', 0)
            ->get()
            ->map(function ($e) use ($today) {
                $end = $e->date_of_joining->copy()->addDays((int) $e->probation_period_days);
                return [
                    'employee_id' => $e->id,
                    'name'        => $e->name,
                    'ends_on'     => $end->toDateString(),
                    'days_left'   => (int) $today->diffInDays($end, false),
                ];
            })
            ->filter(fn ($p) => $p['days_left'] >= 0 && $p['days_left'] <= $window)
            ->sortBy('days_left')->values();

        $contracts = Contract::where('state', 'open')
            ->whereNotNull('date_end')
            ->whereBetween('date_end', [$today, (clone $today)->addDays($window)])
            ->orderBy('date_end')->get()
            ->map(fn ($c) => [
                'contract_id'   => $c->id,
                'employee_name' => $c->employee_name,
                'ends_on'       => $c->date_end->toDateString(),
                'days_left'     => (int) $today->diffInDays($c->date_end, false),
            ])->values();

        return response()->json(['data' => [
            'probation_ending'   => $probation,
            'contracts_expiring' => $contracts,
        ]]);
    }

    // ─── Leaves ──────────────────────────────────────────────

    public function leaves(Request $request): JsonResponse
    {
        // Same visibility scope for the list and the stat cards.
        $scope = fn () => $this->leavesScope($request);

        $page = $this->leavesQuery($request)->paginate(min((int) $request->get('per_page', 20), 100))
            ->withQueryString()->through(fn ($l) => $this->leaveSummary($l));

        return response()->json($page->toArray() + [
            'totals' => [
                'total'    => $scope()->count(),
                'pending'  => $scope()->where('state', 'confirm')->count(),
                'approved' => $scope()->where('state', 'validate')->count(),
                'refused'  => $scope()->where('state', 'refuse')->count(),
                'today'    => $this->onLeaveToday($scope())->count(),
            ],
        ]);
    }

    /** Same dataset as the leaves list (scoping + filters), as an .xlsx download. */
    public function exportLeaves(Request $request)
    {
        $this->applyLocale($request);

        $rows = $this->leavesQuery($request)->get()->map(fn ($l) => [
            $l->odoo_id,
            $l->employee_name,
            $l->leave_type_name,
            $l->date_from?->format('Y-m-d'),
            $l->date_to?->format('Y-m-d'),
            $l->number_of_days,
            $l->state ? __('Leave state: ' . $l->state) : null,
            $l->description,
        ]);

        return ExcelExport::download('leaves-' . now()->format('Y-m-d') . '.xlsx', [
            '#', __('Employee'), __('Type'), __('From'), __('To'),
            __('Days'), __('Status'), __('Description'),
        ], $rows);
    }

    /** Visibility scope: staff see everything (optionally one employee); others only their own. */
    private function leavesScope(Request $request)
    {
        $q = Leave::query();
        $user = $request->user();
        if (!$user->can('hr.view_all')) {
            $q->where('odoo_employee_id', $user->employeeRecord()?->odoo_id ?? -1);
        } elseif ($empId = $request->get('employee_id')) {
            $q->where('odoo_employee_id', $empId);
        }
        return $q;
    }

    private function onLeaveToday($q)
    {
        $today = now()->startOfDay();
        return $q->where('state', 'validate')
            ->whereDate('date_from', '<=', $today)
            ->whereDate('date_to', '>=', $today);
    }

    /** Scoped leave query with the list endpoint's state filter ('today' pseudo-state included). */
    private function leavesQuery(Request $request)
    {
        $query = $this->leavesScope($request);
        if ($state = $request->get('state')) {
            $state === 'today' ? $this->onLeaveToday($query) : $query->where('state', $state);
        }
        return $query->orderByDesc('odoo_id');
    }

    public function leaveTypes(): JsonResponse
    {
        return response()->json([
            'data' => LeaveType::orderBy('name')->get()
                ->map(fn ($t) => ['odoo_id' => $t->odoo_id, 'name' => $t->name]),
        ]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'       => 'required|integer',
            'holiday_status_id' => 'required|integer',
            'date_from'         => 'required|date',
            'date_to'           => 'required|date|after_or_equal:date_from',
            'name'              => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        if (!$user->can('hr.view_all')) {
            $own = $user->employeeRecord();
            abort_unless($own, 403, __('Your account is not linked to an employee record.'));
            $data['employee_id'] = $own->odoo_id;
        }

        $payload = [
            'employee_id'       => (int) $data['employee_id'],
            'holiday_status_id' => (int) $data['holiday_status_id'],
            'date_from'         => $data['date_from'] . ' 00:00:00',
            'date_to'           => $data['date_to']   . ' 23:59:59',
        ];
        if (!empty($data['name'])) {
            $payload['name'] = $data['name'];
        }

        try {
            $odooId = $this->odoo->create('hr.leave', $payload);
            $this->sync->refreshLeave($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => WebLeaveController::friendlyOdooError($e->getMessage())], 422);
        }

        $leave = Leave::where('odoo_id', $odooId)->first();

        return response()->json(['data' => $leave ? $this->leaveSummary($leave) : null], 201);
    }

    public function approveLeave(int $id): JsonResponse
    {
        $leave = Leave::findOrFail($id);

        try {
            if ($leave->state === 'draft') {
                $this->odoo->executeKw('hr.leave', 'action_confirm', [[$leave->odoo_id]]);
            }
            $this->odoo->executeKw('hr.leave', 'action_approve', [[$leave->odoo_id]]);
            $this->sync->refreshLeave($leave->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => WebLeaveController::friendlyOdooError($e->getMessage())], 422);
        }

        return response()->json(['data' => $this->leaveSummary($leave->fresh())]);
    }

    public function refuseLeave(int $id): JsonResponse
    {
        $leave = Leave::findOrFail($id);

        try {
            $this->odoo->executeKw('hr.leave', 'action_refuse', [[$leave->odoo_id]]);
            $this->sync->refreshLeave($leave->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => WebLeaveController::friendlyOdooError($e->getMessage())], 422);
        }

        return response()->json(['data' => $this->leaveSummary($leave->fresh())]);
    }

    // ─── Attendance ──────────────────────────────────────────

    public function attendances(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownOdooId = $user->can('hr.view_all') ? null : ($user->employeeRecord()?->odoo_id ?? -1);

        $todayBase = Attendance::whereDate('check_in', today())
            ->when($ownOdooId !== null, fn ($q) => $q->where('odoo_employee_id', $ownOdooId));

        $page = $this->attendancesQuery($request)->paginate(min((int) $request->get('per_page', 30), 100))
            ->withQueryString()->through(fn ($a) => $this->attendanceSummary($a));

        return response()->json($page->toArray() + [
            'today' => [
                'present'    => (clone $todayBase)->distinct('odoo_employee_id')->count('odoo_employee_id'),
                'checked_in' => (clone $todayBase)->whereNull('check_out')->count(),
            ],
        ]);
    }

    /** Same dataset as the attendances list (scoping + filters), as an .xlsx download. */
    public function exportAttendances(Request $request)
    {
        $this->applyLocale($request);

        $rows = $this->attendancesQuery($request)->get()->map(fn ($a) => [
            $a->employee_name,
            $a->check_in?->format('Y-m-d H:i'),
            $a->check_out?->format('Y-m-d H:i') ?? __('At work'),
            $a->worked_hours,
        ]);

        return ExcelExport::download('attendances-' . now()->format('Y-m-d') . '.xlsx', [
            __('Employee'), __('Check-in'), __('Check-out'), __('Worked hours'),
        ], $rows);
    }

    /** Attendance query scoped to the current user, with the list endpoint's filters applied. */
    private function attendancesQuery(Request $request)
    {
        $query = Attendance::query();

        $user = $request->user();
        if (!$user->can('hr.view_all')) {
            $query->where('odoo_employee_id', $user->employeeRecord()?->odoo_id ?? -1);
        } elseif ($empId = $request->get('employee_id')) {
            $query->where('odoo_employee_id', $empId);
        }
        if ($date = $request->get('date')) {
            $query->whereDate('check_in', $date);
        }

        return $query->orderByDesc('check_in');
    }

    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate(['employee_id' => 'required|integer']);

        $user = $request->user();
        if (!$user->can('hr.view_all')) {
            $own = $user->employeeRecord();
            abort_unless($own, 403, __('Your account is not linked to an employee record.'));
            $data['employee_id'] = $own->odoo_id;
        }

        try {
            $odooId = $this->odoo->create('hr.attendance', [
                'employee_id' => (int) $data['employee_id'],
                'check_in'    => now()->format('Y-m-d H:i:s'),
            ]);

            $rows = $this->odoo->read('hr.attendance', [$odooId],
                ['id', 'employee_id', 'check_in', 'check_out', 'worked_hours']);

            $attendance = null;
            if (!empty($rows)) {
                $row = $rows[0];
                $attendance = Attendance::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                        'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                        'check_in'         => $row['check_in'],
                        'check_out'        => $row['check_out'] ?: null,
                        'worked_hours'     => $row['worked_hours'] ?? 0,
                        'synced_at'        => now(),
                    ]
                );
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $attendance ? $this->attendanceSummary($attendance) : null], 201);
    }

    public function checkOut(Request $request, int $id): JsonResponse
    {
        $attendance = Attendance::findOrFail($id);

        $user = $request->user();
        if (!$user->can('hr.view_all')
            && (int) $attendance->odoo_employee_id !== (int) ($user->employeeRecord()?->odoo_id ?? -1)) {
            return response()->json(['message' => __('Forbidden')], 403);
        }

        try {
            $this->odoo->write('hr.attendance', [$attendance->odoo_id], [
                'check_out' => now()->format('Y-m-d H:i:s'),
            ]);

            $rows = $this->odoo->read('hr.attendance', [$attendance->odoo_id], ['check_out', 'worked_hours']);
            if (!empty($rows)) {
                $attendance->update([
                    'check_out'    => $rows[0]['check_out'],
                    'worked_hours' => $rows[0]['worked_hours'] ?? 0,
                    'synced_at'    => now(),
                ]);
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->attendanceSummary($attendance->fresh())]);
    }

    // ─── Payslips ────────────────────────────────────────────

    public function payslips(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownOdooId = $user->can('payslips.view') ? null : ($user->employeeRecord()?->odoo_id ?? -1);

        $totalsBase = Payslip::query()
            ->when($ownOdooId !== null, fn ($q) => $q->where('odoo_employee_id', $ownOdooId));

        $page = $this->payslipsQuery($request)->paginate(min((int) $request->get('per_page', 25), 100))
            ->withQueryString()->through(fn ($p) => $this->payslipSummary($p));

        return response()->json($page->toArray() + [
            'totals' => [
                'count'      => (clone $totalsBase)->count(),
                'verify'     => (clone $totalsBase)->where('state', 'verify')->count(),
                'done'       => (clone $totalsBase)->where('state', 'done')->count(),
                'this_month' => (clone $totalsBase)->whereYear('date_from', now()->year)
                                    ->whereMonth('date_from', now()->month)->count(),
                'net_total'  => (float) (clone $totalsBase)->sum('net_total'),
            ],
        ]);
    }

    /** Same dataset as the payslips list (scoping + filters), as an .xlsx download. */
    public function exportPayslips(Request $request)
    {
        $this->applyLocale($request);

        $rows = $this->payslipsQuery($request)->get()->map(fn ($p) => [
            $p->number,
            $p->employee_name,
            $p->date_from?->format('Y-m-d'),
            $p->date_to?->format('Y-m-d'),
            $p->basic_total,
            $p->allowance_total,
            $p->gross_total,
            $p->deduction_total,
            $p->net_total,
            $p->stateLabel(),
        ]);

        return ExcelExport::download('payslips-' . now()->format('Y-m-d') . '.xlsx', [
            __('Number'), __('Employee'), __('From'), __('To'),
            __('Basic'), __('Allowances'), __('Gross'), __('Deductions'), __('Net'),
            __('Status'),
        ], $rows);
    }

    /** Payslip query scoped to the current user, with the list endpoint's filters applied. */
    private function payslipsQuery(Request $request)
    {
        $query = Payslip::query();

        $user = $request->user();
        $ownOdooId = null;
        if (!$user->can('payslips.view')) {
            $ownOdooId = $user->employeeRecord()?->odoo_id ?? -1;
            $query->where('odoo_employee_id', $ownOdooId);
        }

        if ($state = $request->get('state')) {
            $query->where('state', $state);
        }
        if ($ownOdooId === null && ($empId = $request->get('employee_id'))) {
            $query->where('odoo_employee_id', $empId);
        }
        if ($month = $request->get('month')) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $query->whereBetween('date_from', [$start, (clone $start)->endOfMonth()]);
        }

        return $query->orderByDesc('odoo_id');
    }

    public function payslip(Request $request, int $id): JsonResponse
    {
        $payslip = Payslip::with('lines')->findOrFail($id);

        $user = $request->user();
        if (!$user->can('payslips.view')
            && (int) $payslip->odoo_employee_id !== (int) ($user->employeeRecord()?->odoo_id ?? -1)) {
            return response()->json(['message' => __('You can only view your own payslips.')], 403);
        }

        return response()->json(['data' => $this->payslipSummary($payslip) + [
            'lines' => $payslip->lines->map(fn ($l) => [
                'code'          => $l->code,
                'name'          => $l->name,
                'category_code' => $l->category_code,
                'total'         => $l->total,
            ]),
        ]]);
    }

    /** Exports run under the SPA's locale (mj_locale cookie → ?lang=) so headers/labels match the UI. */
    private function applyLocale(Request $request): void
    {
        if (in_array($lang = $request->get('lang'), ['ar', 'en'], true)) {
            app()->setLocale($lang);
        }
    }

    // ─── Shapes ──────────────────────────────────────────────

    protected function employeeSummary(Employee $e): array
    {
        return [
            'id'              => $e->id,
            'odoo_id'         => $e->odoo_id,
            'emp_code'        => $e->emp_code,
            'name'            => $e->name,
            'job_title'       => $e->job_title,
            'department'      => $e->department_name,
            'department_odoo_id' => $e->odoo_department_id,
            'company'         => $e->company_name,
            'company_odoo_id' => $e->odoo_company_id,
            'manager'         => $e->parent_name,
            'manager_odoo_id' => $e->odoo_parent_id,
            'work_email'      => $e->work_email,
            'work_phone'      => $e->work_phone,
            'mobile_phone'    => $e->mobile_phone,
            'work_location'   => $e->work_location_name,
            'work_location_odoo_id' => $e->odoo_work_location_id,
            'contract_status' => $e->contract_status,
            'geofence_exempt' => (bool) $e->geofence_exempt,
            'iqama_expiry_date'    => $e->iqama_expiry_date?->toDateString(),
            'license_expiry_date'  => $e->license_expiry_date?->toDateString(),
            'passport_expiry_date' => $e->passport_expiry_date?->toDateString(),
            'active'          => (bool) $e->active,
            'avatar'          => $e->avatar_data_uri,
        ];
    }

    protected function leaveSummary(Leave $l): array
    {
        return [
            'id'             => $l->id,
            'odoo_id'        => $l->odoo_id,
            'employee_name'  => $l->employee_name,
            'leave_type'     => $l->leave_type_name,
            'date_from'      => $l->date_from?->toDateString(),
            'date_to'        => $l->date_to?->toDateString(),
            'number_of_days' => $l->number_of_days,
            'state'          => $l->state,
            'description'    => $l->description,
        ];
    }

    protected function attendanceSummary(Attendance $a): array
    {
        return [
            'id'            => $a->id,
            'employee_name' => $a->employee_name,
            'check_in'      => $a->check_in?->toIso8601String(),
            'check_out'     => $a->check_out?->toIso8601String(),
            'worked_hours'  => $a->worked_hours,
        ];
    }

    protected function payslipSummary(Payslip $p): array
    {
        return [
            'id'              => $p->id,
            'number'          => $p->number,
            'employee_name'   => $p->employee_name,
            'date_from'       => $p->date_from?->toDateString(),
            'date_to'         => $p->date_to?->toDateString(),
            'basic_total'     => $p->basic_total,
            'allowance_total' => $p->allowance_total,
            'gross_total'     => $p->gross_total,
            'deduction_total' => $p->deduction_total,
            'net_total'       => $p->net_total,
            'state'           => $p->state,
            'payment_status'  => $p->payment_status,
            'amount_paid'     => (float) $p->amount_paid,
            'amount_due'      => (float) $p->amount_due,
        ];
    }
}
