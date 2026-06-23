<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\ExcelExport;
use App\Services\OdooService;
use Illuminate\Http\Request;
use RuntimeException;

class AttendanceController extends Controller
{
    public function __construct(protected OdooService $odoo) {}

    public function index(Request $request)
    {
        // Plain employees only see their own records (and own today-stats).
        $user = $request->user();
        $ownOdooId = $user->can('hr.view_all') ? null : ($user->employeeRecord()?->odoo_id ?? -1);

        $attendances = $this->filteredQuery($request)->paginate(30)->withQueryString();
        $employees = $ownOdooId === null
            ? Employee::where('active', true)->orderBy('name')->get()
            : Employee::where('odoo_id', $ownOdooId)->get();

        // إحصائيات اليوم
        $todayBase = Attendance::whereDate('check_in', today())
            ->when($ownOdooId !== null, fn ($q) => $q->where('odoo_employee_id', $ownOdooId));
        $todayStats = [
            'present'    => (clone $todayBase)->distinct('odoo_employee_id')->count('odoo_employee_id'),
            'checked_in' => (clone $todayBase)->whereNull('check_out')->count(),
        ];

        return view('attendances.index', compact('attendances', 'employees', 'todayStats'));
    }

    /** Same dataset as index (own-records scoping + filters), as an .xlsx download. */
    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)->get()->map(fn ($a) => [
            $a->employee_name,
            $a->check_in?->format('Y-m-d H:i'),
            $a->check_out?->format('Y-m-d H:i') ?? __('At work'),
            $a->worked_hours,
        ]);

        return ExcelExport::download('attendances-' . now()->format('Y-m-d') . '.xlsx', [
            __('Employee'), __('Check-in'), __('Check-out'), __('Worked hours'),
        ], $rows);
    }

    /** Attendance query scoped to the current user, with the index page's filters applied. */
    private function filteredQuery(Request $request)
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

    /**
     * Check-in: تسجيل حضور لموظف الآن
     */
    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
        ]);

        // Plain employees can only check themselves in, whatever was posted.
        $user = $request->user();
        if (!$user->can('hr.view_all')) {
            $own = $user->employeeRecord();
            abort_unless($own, 403, __('Your account is not linked to an employee record.'));
            $data['employee_id'] = $own->odoo_id;
        }

        try {
            $odooId = $this->odoo->create('hr.attendance', [
                'employee_id' => (int) $data['employee_id'], // form ids arrive as strings
                'check_in'    => now()->format('Y-m-d H:i:s'),
            ]);

            // جلب البيانات الجديدة وحفظها محلياً
            $rows = $this->odoo->read('hr.attendance', [$odooId],
                ['id', 'employee_id', 'check_in', 'check_out', 'worked_hours']);

            if (!empty($rows)) {
                $row = $rows[0];
                Attendance::updateOrCreate(
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
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return back()->with('status', __('Attendance check-in recorded'));
    }

    /**
     * Check-out: تسجيل انصراف
     */
    public function checkOut(int $id)
    {
        $attendance = Attendance::findOrFail($id);

        // Plain employees can only check out their own open record.
        $user = auth()->user();
        if (!$user->can('hr.view_all')
            && (int) $attendance->odoo_employee_id !== (int) ($user->employeeRecord()?->odoo_id ?? -1)) {
            abort(403);
        }

        try {
            $now = now()->format('Y-m-d H:i:s');
            $this->odoo->write('hr.attendance', [$attendance->odoo_id], [
                'check_out' => $now,
            ]);

            // إعادة جلب البيانات (لحساب worked_hours)
            $rows = $this->odoo->read('hr.attendance', [$attendance->odoo_id],
                ['check_out', 'worked_hours']);

            if (!empty($rows)) {
                $attendance->update([
                    'check_out'    => $rows[0]['check_out'],
                    'worked_hours' => $rows[0]['worked_hours'] ?? 0,
                    'synced_at'    => now(),
                ]);
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return back()->with('status', __('Check-out recorded'));
    }

    public function destroy(int $id)
    {
        $attendance = Attendance::findOrFail($id);

        try {
            $this->odoo->unlink('hr.attendance', [$attendance->odoo_id]);
            $attendance->delete();
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return back()->with('status', __('Record deleted'));
    }
}
