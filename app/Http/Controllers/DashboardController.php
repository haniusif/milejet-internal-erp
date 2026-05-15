<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\SyncLog;
use App\Services\SyncService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'employees'       => Employee::where('active', true)->count(),
            'departments'     => Department::count(),
            'pending_leaves'  => Leave::where('state', 'confirm')->count(),
            'approved_leaves' => Leave::where('state', 'validate')->count(),
            'today_attendance' => Attendance::whereDate('check_in', today())->count(),
        ];

        $lastSync = SyncLog::where('status', 'success')->latest()->first();

        $recentLeaves = Leave::where('state', 'confirm')
            ->latest()->limit(5)->get();

        return view('dashboard', compact('stats', 'lastSync', 'recentLeaves'));
    }

    public function sync(Request $request, SyncService $sync)
    {
        $model = $request->get('model', 'all');

        try {
            match ($model) {
                'departments' => $sync->syncDepartments(),
                'employees'   => $sync->syncEmployees(),
                'leaves'      => $sync->syncLeaves(),
                'attendances' => $sync->syncAttendances(),
                'leave_types' => $sync->syncLeaveTypes(),
                'contracts'   => $sync->syncContracts(),
                'payslips'    => $sync->syncPayslips(),
                default       => $sync->syncAll(),
            };
        } catch (\Throwable $e) {
            return back()->withErrors(['sync' => 'فشلت المزامنة: ' . $e->getMessage()]);
        }

        return back()->with('status', "✅ تمت المزامنة ({$model})");
    }
}
