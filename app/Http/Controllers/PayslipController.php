<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\Payslip;
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
        $query = Payslip::query();

        if ($state = $request->get('state')) {
            $query->where('state', $state);
        }

        if ($empId = $request->get('employee_id')) {
            $query->where('odoo_employee_id', $empId);
        }

        if ($month = $request->get('month')) {
            // expects YYYY-MM
            $query->where('date_from', '>=', $month . '-01')
                  ->where('date_from', '<=', $month . '-31');
        }

        $payslips = $query->orderByDesc('odoo_id')->paginate(25)->withQueryString();
        $employees = Employee::where('active', true)->orderBy('name')->get();

        $totals = [
            'count'    => Payslip::count(),
            'this_month' => Payslip::whereYear('date_from', now()->year)
                                ->whereMonth('date_from', now()->month)->count(),
            'net_total'  => (float) Payslip::sum('net_total'),
        ];

        return view('payslips.index', compact('payslips', 'employees', 'totals'));
    }

    public function show(int $id)
    {
        $payslip = Payslip::with('lines')->findOrFail($id);
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
                'employee_id' => $data['employee_id'],
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
