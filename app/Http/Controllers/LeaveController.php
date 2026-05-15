<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use RuntimeException;

class LeaveController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    public function index(Request $request)
    {
        $query = Leave::query();

        if ($state = $request->get('state')) {
            $query->where('state', $state);
        }

        if ($empId = $request->get('employee_id')) {
            $query->where('odoo_employee_id', $empId);
        }

        $leaves = $query->orderByDesc('odoo_id')->paginate(20)->withQueryString();
        $employees = Employee::where('active', true)->orderBy('name')->get();

        return view('leaves.index', compact('leaves', 'employees'));
    }

    public function create()
    {
        $employees = Employee::where('active', true)->orderBy('name')->get();
        $leaveTypes = LeaveType::orderBy('name')->get();
        return view('leaves.create', compact('employees', 'leaveTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'       => 'required|integer',
            'holiday_status_id' => 'required|integer',
            'date_from'         => 'required|date',
            'date_to'           => 'required|date|after_or_equal:date_from',
            'name'              => 'nullable|string|max:500',
        ]);

        $payload = [
            'employee_id'       => $data['employee_id'],
            'holiday_status_id' => $data['holiday_status_id'],
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
            return back()->withInput()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('leaves.index')
            ->with('status', __('Leave request created'));
    }

    public function approve(int $id)
    {
        $leave = Leave::findOrFail($id);

        try {
            // confirm أولاً إذا كان draft
            if ($leave->state === 'draft') {
                $this->odoo->executeKw('hr.leave', 'action_confirm', [[$leave->odoo_id]]);
            }
            $this->odoo->executeKw('hr.leave', 'action_approve', [[$leave->odoo_id]]);
            $this->sync->refreshLeave($leave->odoo_id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('leaves.index')
            ->with('status', __('Leave approved'));
    }

    public function refuse(int $id)
    {
        $leave = Leave::findOrFail($id);

        try {
            $this->odoo->executeKw('hr.leave', 'action_refuse', [[$leave->odoo_id]]);
            $this->sync->refreshLeave($leave->odoo_id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('leaves.index')->with('status', __('Leave refused'));
    }

    public function destroy(int $id)
    {
        $leave = Leave::findOrFail($id);

        try {
            $this->odoo->unlink('hr.leave', [$leave->odoo_id]);
            $leave->delete();
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('leaves.index')->with('status', __('Leave request deleted'));
    }
}
