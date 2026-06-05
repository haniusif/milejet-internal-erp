<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Payslip;
use App\Models\WorkLocation;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use RuntimeException;

class EmployeeController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    public function index(Request $request)
    {
        $query = Employee::query();

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

        $employees = $query->orderByDesc('odoo_id')->paginate(20)->withQueryString();
        $departments = Department::orderBy('name')->get();

        return view('employees.index', compact('employees', 'departments'));
    }

    public function orgChart()
    {
        $employees = Employee::where('active', true)
            ->orderBy('name')
            ->get(['id', 'odoo_id', 'emp_code', 'name', 'job_title', 'department_name', 'odoo_parent_id', 'image_small']);

        $byParent = $employees->groupBy('odoo_parent_id');
        $activeIds = $employees->pluck('odoo_id')->flip();

        // Roots: no manager, or manager not among active employees
        $roots = $employees->filter(
            fn ($e) => !$e->odoo_parent_id || !isset($activeIds[$e->odoo_parent_id])
        )->values();

        return view('employees.org_chart', compact('roots', 'byParent'));
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get();
        $managers = Employee::where('active', true)->orderBy('name')->get();
        $workLocations = WorkLocation::where('active', true)->orderBy('name')->get();
        return view('employees.create', compact('departments', 'managers', 'workLocations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'work_email'    => 'nullable|email|max:255',
            'work_phone'    => 'nullable|string|max:64',
            'mobile_phone'  => 'nullable|string|max:64',
            'job_title'     => 'nullable|string|max:255',
            'department_id' => 'nullable|integer',
            'parent_id'     => 'nullable|integer',
            'work_location_id' => 'nullable|integer',
        ]);

        $payload = array_filter($data, fn($v) => $v !== null && $v !== '');

        try {
            $odooId = $this->odoo->create('hr.employee', $payload);
            $this->sync->refreshEmployee($odooId);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('employees.index')
            ->with('status', __('Employee created (ID: :id)', ['id' => $odooId]));
    }

    public function show(int $id)
    {
        $employee = Employee::findOrFail($id);

        // Pull extra fields from Odoo on demand (not in local cache)
        $extra = [
            'identification_id' => null,
            'birthday'          => null,
            'country_name'      => null,
            'gender'            => null,
            'create_date'       => null,
            'image'             => null, // base64 PNG, ready for data: URI
        ];
        try {
            $rows = $this->odoo->read('hr.employee', [$employee->odoo_id],
                ['identification_id', 'birthday', 'country_id', 'gender', 'create_date', 'image_256']);
            if (!empty($rows[0])) {
                $row = $rows[0];
                $extra['identification_id'] = $row['identification_id'] ?: null;
                $extra['birthday']          = $row['birthday'] ?: null;
                $extra['country_name']      = OdooService::many2oneName($row['country_id']);
                $extra['gender']            = $row['gender'] ?: null;
                $extra['create_date']       = $row['create_date'] ?: null;
                // Skip Odoo's default placeholder silhouettes (~424 chars).
                // Real uploaded photos are typically > 2 KB base64.
                $img = $row['image_256'] ?? false;
                if (is_string($img) && strlen($img) > 1500) {
                    $extra['image'] = $img;
                }
            }
        } catch (\Throwable) {
            // page renders without these — non-fatal
        }

        $contract = Contract::where('odoo_employee_id', $employee->odoo_id)
            ->orderByRaw("CASE state WHEN 'open' THEN 0 WHEN 'pending' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->latest('odoo_id')->first();

        $recentLeaves = Leave::where('odoo_employee_id', $employee->odoo_id)
            ->orderByDesc('date_from')->limit(5)->get();

        $recentAttendances = Attendance::where('odoo_employee_id', $employee->odoo_id)
            ->orderByDesc('check_in')->limit(5)->get();

        $recentPayslips = Payslip::where('odoo_employee_id', $employee->odoo_id)
            ->orderByDesc('date_from')->limit(5)->get();

        // Org context
        $manager = $employee->odoo_parent_id
            ? Employee::where('odoo_id', $employee->odoo_parent_id)->first()
            : null;
        $reports = $employee->subordinates()->where('active', true)->orderBy('name')->get();

        // Compensation, IDs and other private data: HR/payroll roles, or the
        // employee viewing their own profile (matched by work email).
        $user = auth()->user();
        $isSelf = $employee->work_email
            && strcasecmp($employee->work_email, $user->email) === 0;
        $canViewSensitive = $isSelf || $user->can('employees.view_sensitive');

        return view('employees.show', compact(
            'employee', 'extra', 'contract', 'recentLeaves', 'recentAttendances',
            'recentPayslips', 'manager', 'reports', 'canViewSensitive'
        ));
    }

    public function edit(int $id)
    {
        $employee = Employee::findOrFail($id);
        $departments = Department::orderBy('name')->get();
        $managers = Employee::where('active', true)
            ->where('odoo_id', '!=', $employee->odoo_id)
            ->orderBy('name')->get();
        $workLocations = WorkLocation::where('active', true)->orderBy('name')->get();

        return view('employees.edit', compact('employee', 'departments', 'managers', 'workLocations'));
    }

    public function update(Request $request, int $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'work_email'    => 'nullable|email|max:255',
            'work_phone'    => 'nullable|string|max:64',
            'mobile_phone'  => 'nullable|string|max:64',
            'job_title'     => 'nullable|string|max:255',
            'department_id' => 'nullable|integer',
            'parent_id'     => 'nullable|integer',
            'work_location_id' => 'nullable|integer',
        ]);

        // تحويل القيم الفارغة للـ many2one إلى false (لفك الربط في Odoo)
        $payload = $data;
        foreach (['department_id', 'parent_id', 'work_location_id'] as $f) {
            if (empty($payload[$f])) $payload[$f] = false;
        }

        try {
            $this->odoo->write('hr.employee', [$employee->odoo_id], $payload);
            $this->sync->refreshEmployee($employee->odoo_id);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('employees.index')
            ->with('status', __('Employee details updated'));
    }

    public function destroy(int $id)
    {
        $employee = Employee::findOrFail($id);

        try {
            $this->odoo->unlink('hr.employee', [$employee->odoo_id]);
            $employee->delete();
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('employees.index')
            ->with('status', __('Employee deleted'));
    }
}
