<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\ExcelExport;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use RuntimeException;

class PayslipController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    public function index(Request $request)
    {
        // Users without the payroll gate only see their own payslips.
        $user = $request->user();
        $ownOdooId = $user->can('payslips.view') ? null : ($user->employeeRecord()?->odoo_id ?? -1);

        $payslips = $this->filteredQuery($request)->paginate(25)->withQueryString();
        $employees = $ownOdooId === null
            ? Employee::where('active', true)->orderBy('name')->get()
            : collect();

        $totalsBase = Payslip::query()
            ->when($ownOdooId !== null, fn ($q) => $q->where('odoo_employee_id', $ownOdooId));
        $totals = [
            'count'    => (clone $totalsBase)->count(),
            'this_month' => (clone $totalsBase)->whereYear('date_from', now()->year)
                                ->whereMonth('date_from', now()->month)->count(),
            'net_total'  => (float) (clone $totalsBase)->sum('net_total'),
        ];

        return view('payslips.index', compact('payslips', 'employees', 'totals'));
    }

    /** Same dataset as index (own-records scoping + filters), as an .xlsx download. */
    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)->get()->map(fn ($p) => [
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

    /** Payslip query scoped to the current user, with the index page's filters applied. */
    private function filteredQuery(Request $request)
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

        $period = $request->get('period');
        $month  = $request->get('month');

        if ($period === 'current') {
            $month = now()->format('Y-m');
        } elseif ($period === 'last') {
            $month = now()->subMonthNoOverflow()->format('Y-m');
        }

        if ($month) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end   = (clone $start)->endOfMonth();
            $query->whereBetween('date_from', [$start, $end]);
        }

        return $query->orderByDesc('odoo_id');
    }

    public function show(int $id)
    {
        $payslip = Payslip::with('lines')->findOrFail($id);

        // Users without the payroll gate can only open their own payslips.
        $user = auth()->user();
        if (!$user->can('payslips.view')
            && (int) $payslip->odoo_employee_id !== (int) ($user->employeeRecord()?->odoo_id ?? -1)) {
            abort(403, __('You can only view your own payslips.'));
        }

        return view('payslips.show', compact('payslip'));
    }

    public function create()
    {
        $employees = Employee::where('active', true)
            ->whereIn('odoo_id', Contract::where('state', 'open')->pluck('odoo_employee_id'))
            ->orderBy('name')->get();

        return view('payslips.create', [
            'employees' => $employees,
            'default_from' => now()->startOfMonth()->format('Y-m-d'),
            'default_to'   => now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request)
    {
        // Bulk path: when employee_ids[] is provided, create one payslip per id.
        if ($request->has('employee_ids')) {
            return $this->bulkStore($request);
        }

        $data = $request->validate([
            'employee_id' => 'required|integer',
            'date_from'   => 'required|date',
            'date_to'     => 'required|date|after_or_equal:date_from',
            'compute'     => 'nullable|boolean',
        ]);

        // Find the employee's active contract for struct_id
        $contract = Contract::where('odoo_employee_id', $data['employee_id'])
            ->where('state', 'open')->latest('odoo_id')->first();

        if (!$contract) {
            return back()->withInput()
                ->withErrors(['employee_id' => __('No active contract for this employee')]);
        }

        try {
            $payload = [
                'employee_id' => (int) $data['employee_id'], // form ids arrive as strings
                'date_from'   => $data['date_from'],
                'date_to'     => $data['date_to'],
                'contract_id' => $contract->odoo_id,
            ];
            if ($contract->odoo_struct_id) {
                $payload['struct_id'] = $contract->odoo_struct_id;
            }

            $odooId = $this->odoo->create('hr.payslip', $payload);

            // Auto-compute lines (default on)
            if ($request->boolean('compute', true)) {
                $this->odoo->executeKw('hr.payslip', 'compute_sheet', [[$odooId]]);
            }

            $this->sync->refreshPayslip($odooId);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => $e->getMessage()]);
        }

        $local = Payslip::where('odoo_id', $odooId)->first();

        return redirect()->route('payslips.show', $local->id)
            ->with('status', __('Payslip created'));
    }

    protected function bulkStore(Request $request)
    {
        $data = $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'integer',
            'date_from'      => 'required|date',
            'date_to'        => 'required|date|after_or_equal:date_from',
            'compute'        => 'nullable|boolean',
        ]);

        $compute = $request->boolean('compute', true);
        $created = [];
        $skipped = [];
        $failed = [];

        $contracts = Contract::where('state', 'open')
            ->whereIn('odoo_employee_id', $data['employee_ids'])
            ->get()
            ->keyBy('odoo_employee_id');

        foreach ($data['employee_ids'] as $empOdooId) {
            $empOdooId = (int) $empOdooId;
            $contract = $contracts->get($empOdooId);
            $emp = Employee::where('odoo_id', $empOdooId)->first();
            $label = $emp?->name ?? "employee_id={$empOdooId}";

            if (!$contract) {
                $skipped[] = [$label, __('No active contract')];
                continue;
            }

            try {
                $payload = [
                    'employee_id' => $empOdooId,
                    'date_from'   => $data['date_from'],
                    'date_to'     => $data['date_to'],
                    'contract_id' => $contract->odoo_id,
                ];
                if ($contract->odoo_struct_id) {
                    $payload['struct_id'] = $contract->odoo_struct_id;
                }
                $odooId = $this->odoo->create('hr.payslip', $payload);

                if ($compute) {
                    $this->odoo->executeKw('hr.payslip', 'compute_sheet', [[$odooId]]);
                }
                $this->sync->refreshPayslip($odooId);
                $created[] = [$label, $odooId];
            } catch (RuntimeException $e) {
                $failed[] = [$label, $e->getMessage()];
            }
        }

        $msg = __(':created created, :skipped skipped, :failed failed', [
            'created' => count($created),
            'skipped' => count($skipped),
            'failed'  => count($failed),
        ]);

        $request->session()->flash('bulk_result', [
            'created' => $created,
            'skipped' => $skipped,
            'failed'  => $failed,
        ]);

        return redirect()->route('payslips.index', [
            'month' => substr((string) $data['date_from'], 0, 7),
        ])->with('status', $msg);
    }

    public function compute(int $id)
    {
        $payslip = Payslip::findOrFail($id);
        try {
            $this->odoo->executeKw('hr.payslip', 'compute_sheet', [[$payslip->odoo_id]]);
            $this->sync->refreshPayslip($payslip->odoo_id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return back()->with('status', __('Recomputed successfully'));
    }

    public function destroy(int $id)
    {
        $payslip = Payslip::findOrFail($id);
        try {
            // Cancel first (Odoo only allows delete on draft/cancel)
            if (in_array($payslip->state, ['verify', 'done'])) {
                $this->odoo->executeKw('hr.payslip', 'action_payslip_cancel', [[$payslip->odoo_id]]);
            }
            $this->odoo->unlink('hr.payslip', [$payslip->odoo_id]);
            $payslip->delete();
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('payslips.index')->with('status', __('Deleted'));
    }
}
