<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request)
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

        $contracts = $query->orderByDesc('odoo_id')->paginate(25)->withQueryString();
        $employees = Employee::where('active', true)->orderBy('name')->get();

        $totals = [
            'count'         => Contract::count(),
            'open'          => Contract::where('state', 'open')->count(),
            'total_wage'    => (float) Contract::where('state', 'open')->sum('wage'),
        ];

        return view('contracts.index', compact('contracts', 'employees', 'totals'));
    }
}
