<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Services\ExcelExport;
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
        $user = $request->user();
        $leaves = $this->filteredQuery($request)->paginate(20)->withQueryString();
        $employees = $user->can('hr.view_all')
            ? Employee::where('active', true)->orderBy('name')->get()
            : collect();

        // [hr.leave odoo id => [{id, name, mimetype}, ...]] for the current page.
        $attachments = $this->attachmentsFor(
            $leaves->getCollection()->pluck('odoo_id')->filter()->values()->all()
        );

        return view('leaves.index', compact('leaves', 'employees', 'attachments'));
    }

    /** Same dataset as index (own-records scoping + filters), as an .xlsx download. */
    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)->get()->map(fn ($l) => [
            $l->odoo_id,
            $l->employee_name,
            $l->leave_type_name,
            $l->date_from?->format('Y-m-d'),
            $l->date_to?->format('Y-m-d'),
            $l->number_of_days,
            $l->stateLabel(),
            $l->description,
        ]);

        return ExcelExport::download('leaves-' . now()->format('Y-m-d') . '.xlsx', [
            '#', __('Employee'), __('Type'), __('From'), __('To'),
            __('Days'), __('Status'), __('Description'),
        ], $rows);
    }

    /** Leave query scoped to the current user, with the index page's filters applied. */
    private function filteredQuery(Request $request)
    {
        $query = Leave::query();

        // Plain employees only see their own requests.
        $user = $request->user();
        if (!$user->can('hr.view_all')) {
            $query->where('odoo_employee_id', $user->employeeRecord()?->odoo_id ?? -1);
        } elseif ($empId = $request->get('employee_id')) {
            $query->where('odoo_employee_id', $empId);
        }

        if ($state = $request->get('state')) {
            $query->where('state', $state);
        }

        return $query->orderByDesc('odoo_id');
    }

    /**
     * Turns a raw Odoo fault message into a friendly, translatable one for the
     * dashboard. Falls back to the original (minus the technical prefix).
     */
    public static function friendlyOdooError(string $raw): string
    {
        $lower = strtolower($raw);

        if (str_contains($lower, 'not supposed to work') || str_contains($raw, 'غير مفترض')) {
            return __('This time off falls on non-working days in the employee\'s schedule, so it can\'t be approved. Check the dates or the work calendar (Saudi work week is Sun–Thu).');
        }
        if (str_contains($lower, 'allocation') || str_contains($raw, 'تخصيص')) {
            return __('No leave balance available for this type. Please allocate days for the employee first.');
        }
        if (str_contains($lower, 'overlap') || str_contains($raw, 'تداخل')) {
            return __('These dates overlap an existing leave request.');
        }
        if (str_contains($lower, 'duration') && str_contains($lower, '0')) {
            return __('This leave has zero working days for the selected period.');
        }

        // Strip the "خطأ في model.method: " prefix added by OdooService.
        return trim(preg_replace('/^خطأ في [\w.]+:\s*/u', '', $raw));
    }

    /** Batch-load ir.attachment metadata for the given hr.leave odoo ids. */
    private function attachmentsFor(array $leaveOdooIds): array
    {
        if (empty($leaveOdooIds)) {
            return [];
        }
        try {
            $rows = $this->odoo->useServiceAccount()->searchRead(
                'ir.attachment',
                [['res_model', '=', 'hr.leave'], ['res_id', 'in', $leaveOdooIds]],
                ['id', 'name', 'mimetype', 'res_id']
            );
        } catch (\Throwable $e) {
            report($e);
            return [];
        }

        $map = [];
        foreach ($rows as $r) {
            $resId = is_array($r['res_id'] ?? null) ? ($r['res_id'][0] ?? null) : ($r['res_id'] ?? null);
            if ($resId === null) {
                continue;
            }
            $map[$resId][] = $r;
        }
        return $map;
    }

    /** Streams a leave attachment's file inline (for the dashboard). */
    public function attachment(int $id)
    {
        try {
            $rows = $this->odoo->useServiceAccount()->read('ir.attachment', [$id],
                ['name', 'mimetype', 'res_model', 'res_id', 'datas']);
        } catch (\Throwable $e) {
            abort(404);
        }
        if (empty($rows[0]) || ($rows[0]['res_model'] ?? null) !== 'hr.leave' || empty($rows[0]['datas'])) {
            abort(404);
        }
        $att = $rows[0];

        // Plain employees can only open attachments on their own leaves.
        $user = auth()->user();
        if (!$user->can('hr.view_all')) {
            $resId = is_array($att['res_id'] ?? null) ? ($att['res_id'][0] ?? null) : ($att['res_id'] ?? null);
            $owns = $resId && Leave::where('odoo_id', $resId)
                ->where('odoo_employee_id', $user->employeeRecord()?->odoo_id ?? -1)
                ->exists();
            abort_unless($owns, 403);
        }

        return response(base64_decode($att['datas']), 200, [
            'Content-Type'        => $att['mimetype'] ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . ($att['name'] ?: 'attachment') . '"',
        ]);
    }

    public function create()
    {
        // Plain employees can only request leave for themselves.
        $user = auth()->user();
        if (!$user->can('hr.view_all')) {
            $own = $user->employeeRecord();
            abort_unless($own, 403, __('Your account is not linked to an employee record.'));
            $employees = collect([$own]);
        } else {
            $employees = Employee::where('active', true)->orderBy('name')->get();
        }

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

        // Plain employees can only file for themselves, whatever was posted.
        $user = $request->user();
        if (!$user->can('hr.view_all')) {
            $own = $user->employeeRecord();
            abort_unless($own, 403, __('Your account is not linked to an employee record.'));
            $data['employee_id'] = $own->odoo_id;
        }

        $payload = [
            // form ids arrive as strings — Odoo needs ints for many2one
            'employee_id'       => (int) $data['employee_id'],
            'holiday_status_id' => (int) $data['holiday_status_id'],
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
            return back()->withInput()->withErrors(['odoo' => $this->friendlyOdooError($e->getMessage())]);
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
            return back()->withErrors(['odoo' => $this->friendlyOdooError($e->getMessage())]);
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
            return back()->withErrors(['odoo' => $this->friendlyOdooError($e->getMessage())]);
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
            return back()->withErrors(['odoo' => $this->friendlyOdooError($e->getMessage())]);
        }

        return redirect()->route('leaves.index')->with('status', __('Leave request deleted'));
    }
}
