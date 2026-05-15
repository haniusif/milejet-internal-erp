<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\OdooService;
use Illuminate\Http\Request;
use RuntimeException;

class AttendanceController extends Controller
{
    public function __construct(protected OdooService $odoo) {}

    public function index(Request $request)
    {
        $query = Attendance::query();

        if ($empId = $request->get('employee_id')) {
            $query->where('odoo_employee_id', $empId);
        }

        if ($date = $request->get('date')) {
            $query->whereDate('check_in', $date);
        }

        $attendances = $query->orderByDesc('check_in')->paginate(30)->withQueryString();
        $employees = Employee::where('active', true)->orderBy('name')->get();

        // إحصائيات اليوم
        $todayStats = [
            'present'    => Attendance::whereDate('check_in', today())->distinct('odoo_employee_id')->count('odoo_employee_id'),
            'checked_in' => Attendance::whereDate('check_in', today())->whereNull('check_out')->count(),
        ];

        return view('attendances.index', compact('attendances', 'employees', 'todayStats'));
    }

    /**
     * Check-in: تسجيل حضور لموظف الآن
     */
    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
        ]);

        try {
            $odooId = $this->odoo->create('hr.attendance', [
                'employee_id' => $data['employee_id'],
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
