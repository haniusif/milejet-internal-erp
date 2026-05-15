<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use RuntimeException;

class DepartmentController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    public function index()
    {
        $departments = Department::orderBy('name')->get();
        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        $managers = Employee::where('active', true)->orderBy('name')->get();
        $parents = Department::orderBy('name')->get();
        return view('departments.create', compact('managers', 'parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'manager_id' => 'nullable|integer',
            'parent_id'  => 'nullable|integer',
        ]);

        $payload = array_filter($data, fn($v) => $v !== null && $v !== '');

        try {
            $odooId = $this->odoo->create('hr.department', $payload);
            $this->sync->refreshDepartment($odooId);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('departments.index')
            ->with('status', "تم إنشاء القسم");
    }

    public function edit(int $id)
    {
        $department = Department::findOrFail($id);
        $managers = Employee::where('active', true)->orderBy('name')->get();
        $parents = Department::where('odoo_id', '!=', $department->odoo_id)
            ->orderBy('name')->get();

        return view('departments.edit', compact('department', 'managers', 'parents'));
    }

    public function update(Request $request, int $id)
    {
        $department = Department::findOrFail($id);

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'manager_id' => 'nullable|integer',
            'parent_id'  => 'nullable|integer',
        ]);

        $payload = $data;
        foreach (['manager_id', 'parent_id'] as $f) {
            if (empty($payload[$f])) $payload[$f] = false;
        }

        try {
            $this->odoo->write('hr.department', [$department->odoo_id], $payload);
            $this->sync->refreshDepartment($department->odoo_id);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('departments.index')->with('status', "تم تحديث القسم");
    }

    public function destroy(int $id)
    {
        $department = Department::findOrFail($id);

        try {
            $this->odoo->unlink('hr.department', [$department->odoo_id]);
            $department->delete();
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('departments.index')->with('status', "تم حذف القسم");
    }
}
