<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Employee;
use App\Services\ExcelExport;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $contracts = $this->filteredQuery($request)->paginate(25)->withQueryString();
        $employees = Employee::where('active', true)->orderBy('name')->get();

        $totals = [
            'count'         => Contract::count(),
            'open'          => Contract::where('state', 'open')->count(),
            'total_wage'    => (float) Contract::where('state', 'open')->sum('wage'),
        ];

        return view('contracts.index', compact('contracts', 'employees', 'totals'));
    }

    /** Same dataset as index (current filters applied), as an .xlsx download. */
    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)->get()->map(fn ($c) => [
            $c->name,
            $c->employee_name,
            $c->wage,
            $c->date_start?->format('Y-m-d'),
            $c->date_end?->format('Y-m-d'),
            $c->struct_name,
            $c->state ? __('Contract state: ' . $c->state) : null,
        ]);

        return ExcelExport::download('contracts-' . now()->format('Y-m-d') . '.xlsx', [
            __('Reference'), __('Employee'), __('Salary'), __('From'), __('To'),
            __('Structure'), __('Status'),
        ], $rows);
    }

    /** Contract query with the index page's filters applied. */
    private function filteredQuery(Request $request)
    {
        $query = Contract::query();

        if ($state = $request->get('state')) {
            $query->where('state', $state);
        }

        if ($empId = $request->get('employee_id')) {
            $query->where('odoo_employee_id', $empId);
        }

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_name', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('odoo_id');
    }
}
