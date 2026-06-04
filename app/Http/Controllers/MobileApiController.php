<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\OdooRoleMapper;
use App\Services\OdooService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MobileApiController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected OdooRoleMapper $roleMapper,
    ) {}

    // ─── Auth ────────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $uid = $this->odoo->tryAuthenticate($data['email'], $data['password']);
        if (!$uid) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $this->odoo->setCredentials($data['email'], $data['password'], $uid);

        $name     = $data['email'];
        $groupIds = [];
        $jobTitle = null;
        $department = null;
        $odooEmployeeId = null;

        try {
            $odooUser = $this->odoo->read('res.users', [$uid], ['name', 'groups_id']);
            if (!empty($odooUser[0])) {
                $name     = $odooUser[0]['name'] ?? $name;
                $groupIds = $odooUser[0]['groups_id'] ?? [];
            }
        } catch (\Exception) {}

        // Link to hr.employee via user_id field
        try {
            $empRows = $this->odoo->searchRead(
                'hr.employee',
                [['user_id', '=', $uid]],
                ['id', 'job_title', 'department_id'],
                1
            );
            if (!empty($empRows[0])) {
                $odooEmployeeId = $empRows[0]['id'];
                $jobTitle       = $empRows[0]['job_title'] ?: null;
                $deptField      = $empRows[0]['department_id'];
                $department     = is_array($deptField) ? $deptField[1] : null;
            }
        } catch (\Exception) {}

        $roles = ['employee'];
        try {
            if (!empty($groupIds)) {
                $groups = $this->odoo->read('res.groups', $groupIds, ['name', 'category_id']);
                $roles  = $this->roleMapper->rolesFromGroups($groups);
            }
        } catch (\Exception) {}

        $user = User::updateOrCreate(
            ['odoo_uid' => $uid],
            [
                'name'             => $name,
                'email'            => $data['email'],
                'odoo_api_key'     => $data['password'],
                'odoo_employee_id' => $odooEmployeeId,
                'odoo_group_ids'   => $groupIds,
                'roles'            => $roles,
                'roles_synced_at'  => now(),
            ]
        );

        // Also grab avatar and job title from local employees cache
        $emp = $odooEmployeeId
            ? Employee::where('odoo_id', $odooEmployeeId)->first()
            : null;

        $token = $user->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'roles'      => $user->roles,
                'job_title'  => $emp?->job_title ?? $jobTitle,
                'department' => $emp?->department_name ?? $department,
                'avatar'     => $emp?->image_small,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $emp  = $user->odoo_employee_id
            ? Employee::where('odoo_id', $user->odoo_employee_id)->first()
            : null;

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'roles'      => $user->roles,
            'job_title'  => $emp?->job_title,
            'department' => $emp?->department_name,
            'avatar'     => $emp?->image_small,
        ]);
    }

    // ─── Leaves ──────────────────────────────────────────────

    public function leaves(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->odoo_employee_id) {
            return response()->json([]);
        }

        $query = Leave::where('odoo_employee_id', $user->odoo_employee_id)
            ->orderByDesc('date_from');

        if ($state = $request->get('state')) {
            $query->where('state', $state);
        }

        $leaves = $query->limit(50)->get();

        // Batch-fetch attachment metadata for all these leaves in one Odoo call.
        $attachments = $this->leaveAttachmentsMap(
            $leaves->pluck('odoo_id')->filter()->values()->all()
        );

        return response()->json(
            $leaves->map(fn($l) => [
                'id'              => $l->id,
                'employee_name'   => $l->employee_name,
                'leave_type_name' => $l->leave_type_name,
                'date_from'       => $l->date_from?->toIso8601String(),
                'date_to'         => $l->date_to?->toIso8601String(),
                'number_of_days'  => (float) $l->number_of_days,
                'state'           => $l->state,
                'name'            => $l->description,
                'attachments'     => $attachments[$l->odoo_id] ?? [],
            ])
        );
    }

    /**
     * Maps [hr.leave odoo id => [['id','name','mimetype'], ...]] for the given
     * leave ids, read from Odoo ir.attachment via the service account. Empty
     * (and logs) on failure so the leaves list still renders.
     */
    private function leaveAttachmentsMap(array $leaveOdooIds): array
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
            logger()->warning('Fetch leave attachments failed: ' . $e->getMessage());
            return [];
        }

        $map = [];
        foreach ($rows as $r) {
            $resId = is_array($r['res_id'] ?? null) ? ($r['res_id'][0] ?? null) : ($r['res_id'] ?? null);
            if ($resId === null) {
                continue;
            }
            $map[$resId][] = [
                'id'       => $r['id'],
                'name'     => $r['name'] ?? 'attachment',
                'mimetype' => $r['mimetype'] ?: null,
            ];
        }
        return $map;
    }

    /**
     * Returns a single leave attachment's bytes (base64) — only if it belongs
     * to one of the authenticated employee's leaves.
     */
    public function leaveAttachment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->odoo_employee_id) {
            return response()->json(['message' => 'No linked employee'], 422);
        }

        try {
            $rows = $this->odoo->useServiceAccount()->read('ir.attachment', [$id],
                ['name', 'mimetype', 'res_model', 'res_id', 'datas']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }
        if (empty($rows[0])) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }
        $att   = $rows[0];
        $resId = is_array($att['res_id'] ?? null) ? ($att['res_id'][0] ?? null) : ($att['res_id'] ?? null);

        $owns = ($att['res_model'] ?? null) === 'hr.leave'
            && $resId !== null
            && Leave::where('odoo_id', $resId)
                ->where('odoo_employee_id', $user->odoo_employee_id)
                ->exists();
        if (!$owns) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'id'       => $id,
            'name'     => $att['name'] ?? 'attachment',
            'mimetype' => $att['mimetype'] ?: 'application/octet-stream',
            'data'     => $att['datas'] ?? '',
        ]);
    }

    public function leaveTypes(): JsonResponse
    {
        return response()->json(
            LeaveType::orderBy('name')->get()->map(fn($t) => [
                'id'   => $t->id,
                'name' => $t->name,
            ])
        );
    }

    public function createLeave(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->odoo_employee_id) {
            return response()->json(['message' => 'No linked employee'], 422);
        }

        $data = $request->validate([
            'leave_type_id'    => 'required|integer',
            'date_from'        => 'required|date',
            'date_to'          => 'required|date|after_or_equal:date_from',
            'reason'           => 'nullable|string|max:500',
            'attachment'       => 'nullable|array',
            'attachment.name'  => 'required_with:attachment|string|max:255',
            // base64 of a ~5 MB file ≈ 7M chars; cap to bound the request.
            'attachment.data'  => 'required_with:attachment|string|max:8000000',
        ]);

        // Resolve Odoo leave type ID from local ID
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        $payload = [
            'employee_id'       => $user->odoo_employee_id,
            'holiday_status_id' => $leaveType->odoo_id,
            'date_from'         => $data['date_from'] . ' 00:00:00',
            'date_to'           => $data['date_to']   . ' 23:59:59',
        ];
        if (!empty($data['reason'])) {
            $payload['name'] = $data['reason'];
        }

        try {
            $this->odoo->setCredentials(
                $user->email,
                $user->odoo_api_key,
                $user->odoo_uid
            );
            $odooId = $this->odoo->create('hr.leave', $payload);

            // Fetch back and cache
            $rows = $this->odoo->read('hr.leave', [$odooId], [
                'id', 'employee_id', 'holiday_status_id', 'date_from', 'date_to',
                'number_of_days', 'state', 'name',
            ]);

            $leave = null;
            if (!empty($rows[0])) {
                $r = $rows[0];
                $leave = Leave::updateOrCreate(
                    ['odoo_id' => $r['id']],
                    [
                        'odoo_employee_id' => $user->odoo_employee_id,
                        'employee_name'    => $user->name,
                        'odoo_leave_type_id' => $leaveType->odoo_id,
                        'leave_type_name'  => $leaveType->name,
                        'date_from'        => $r['date_from'],
                        'date_to'          => $r['date_to'],
                        'number_of_days'   => $r['number_of_days'] ?? 0,
                        'state'            => $r['state'] ?? 'confirm',
                        'description'      => $r['name'] ?: null,
                        'synced_at'        => now(),
                    ]
                );
            }

            // Attach the uploaded file to the leave record in Odoo. Best-effort:
            // a failure here must not void an otherwise-valid leave request.
            // Created via the service account (employees can't always create
            // ir.attachment) and linked to the leave via res_model/res_id.
            if (!empty($data['attachment']['data'])) {
                try {
                    $this->odoo->useServiceAccount();
                    $this->odoo->create('ir.attachment', [
                        'name'      => $data['attachment']['name'] ?? 'leave-attachment',
                        'datas'     => $data['attachment']['data'], // base64
                        'res_model' => 'hr.leave',
                        'res_id'    => $odooId,
                        'type'      => 'binary',
                    ]);
                } catch (\Throwable $e) {
                    logger()->warning('Leave attachment upload failed: ' . $e->getMessage());
                }
            }

            $days = (int) now()->parse($data['date_from'])->diffInDays($data['date_to']) + 1;
            return response()->json([
                'id'              => $leave?->id ?? 0,
                'employee_name'   => $user->name,
                'leave_type_name' => $leaveType->name,
                'date_from'       => $data['date_from'],
                'date_to'         => $data['date_to'],
                'number_of_days'  => $days,
                'state'           => 'confirm',
                'name'            => $data['reason'] ?? null,
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $this->friendlyLeaveError($e->getMessage())], 422);
        }
    }

    // ─── Attendance ───────────────────────────────────────────

    public function attendanceConfig(Request $request): JsonResponse
    {
        $fence = $this->resolveGeofence($request->user()->odoo_employee_id);

        return response()->json([
            'latitude'    => $fence['lat'],
            'longitude'   => $fence['lng'],
            'radius'      => $fence['radius'],
            'enforce'     => (bool) config('attendance.geofence_enforce'),
            'office_name' => $fence['name'],
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->odoo_employee_id) {
            return response()->json([]);
        }

        $query = Attendance::where('odoo_employee_id', $user->odoo_employee_id)
            ->orderByDesc('check_in');

        if ($date = $request->get('date')) {
            $query->whereDate('check_in', $date);
        }

        return response()->json(
            $query->limit(30)->get()->map(fn($a) => $this->formatAttendance($a))
        );
    }

    public function currentAttendance(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->odoo_employee_id) {
            return response()->json(null);
        }

        $active = Attendance::where('odoo_employee_id', $user->odoo_employee_id)
            ->whereNull('check_out')
            ->latest('check_in')
            ->first();

        return response()->json($active ? $this->formatAttendance($active) : null);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->odoo_employee_id) {
            return response()->json(['message' => 'No linked employee'], 422);
        }

        $loc = $request->validate([
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $lat = $loc['latitude']  ?? null;
        $lng = $loc['longitude'] ?? null;

        if ($error = $this->geofenceError($lat, $lng, $user->odoo_employee_id)) {
            return response()->json(['message' => $error], 422);
        }

        // If already checked in locally, return the existing record (idempotent)
        $existing = Attendance::where('odoo_employee_id', $user->odoo_employee_id)
            ->whereNull('check_out')
            ->latest('check_in')
            ->first();
        if ($existing) {
            return response()->json($this->formatAttendance($existing), 200);
        }

        try {
            // Employees lack hr.attendance create rights in Odoo, so punch via
            // the service account. employee_id is set from the authenticated
            // user server-side, so a user can only ever check in as themselves.
            $this->odoo->useServiceAccount();
            $payload = [
                'employee_id' => $user->odoo_employee_id,
                'check_in'    => now()->utc()->format('Y-m-d H:i:s'),
            ];
            if ($lat !== null && $lng !== null) {
                $payload['in_latitude']  = (float) $lat;
                $payload['in_longitude'] = (float) $lng;
            }
            $odooId = $this->odoo->create('hr.attendance', $payload);

            $rows = $this->odoo->read('hr.attendance', [$odooId],
                ['id', 'employee_id', 'check_in', 'check_out', 'worked_hours']);

            $att = null;
            if (!empty($rows[0])) {
                $r   = $rows[0];
                $att = Attendance::updateOrCreate(
                    ['odoo_id' => $r['id']],
                    [
                        'odoo_employee_id' => $user->odoo_employee_id,
                        'employee_name'    => $user->name,
                        'check_in'         => $r['check_in'],
                        'check_out'        => $r['check_out'] ?: null,
                        'worked_hours'     => $r['worked_hours'] ?? 0,
                        'in_latitude'      => $lat,
                        'in_longitude'     => $lng,
                        'synced_at'        => now(),
                    ]
                );
                $att = $att->fresh(); // ensure auto-increment id is populated
            }

            return response()->json($att ? $this->formatAttendance($att) : null, 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $this->friendlyLeaveError($e->getMessage())], 422);
        }
    }

    public function checkOut(Request $request, int $id): JsonResponse
    {
        if ($id === 0) {
            return response()->json(['message' => 'Invalid attendance record'], 422);
        }
        $att = Attendance::findOrFail($id);
        $user = $request->user();

        $loc = $request->validate([
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $lat = $loc['latitude']  ?? null;
        $lng = $loc['longitude'] ?? null;

        if ($error = $this->geofenceError($lat, $lng, $user->odoo_employee_id)) {
            return response()->json(['message' => $error], 422);
        }

        try {
            // Use the service account — employees can't write hr.attendance.
            $this->odoo->useServiceAccount();
            $writePayload = [
                'check_out' => now()->utc()->format('Y-m-d H:i:s'),
            ];
            if ($lat !== null && $lng !== null) {
                $writePayload['out_latitude']  = (float) $lat;
                $writePayload['out_longitude'] = (float) $lng;
            }
            $this->odoo->write('hr.attendance', [$att->odoo_id], $writePayload);

            $rows = $this->odoo->read('hr.attendance', [$att->odoo_id],
                ['check_out', 'worked_hours']);

            if (!empty($rows[0])) {
                $att->update([
                    'check_out'     => $rows[0]['check_out'],
                    'worked_hours'  => $rows[0]['worked_hours'] ?? 0,
                    'out_latitude'  => $lat,
                    'out_longitude' => $lng,
                    'synced_at'     => now(),
                ]);
            }

            return response()->json($this->formatAttendance($att->fresh()));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ─── Payslips ─────────────────────────────────────────────

    public function payslips(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->odoo_employee_id) {
            return response()->json([]);
        }

        $payslips = Payslip::where('odoo_employee_id', $user->odoo_employee_id)
            ->orderByDesc('date_from')
            ->limit(24)
            ->get();

        return response()->json($payslips->map(fn($p) => $this->formatPayslip($p)));
    }

    public function payslip(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $payslip = Payslip::with('lines')->findOrFail($id);

        if ($payslip->odoo_employee_id !== $user->odoo_employee_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($this->formatPayslip($payslip, withLines: true));
    }

    // ─── Notifications (stub — extend later) ─────────────────

    public function notifications(Request $request): JsonResponse
    {
        return response()->json([]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function friendlyLeaveError(string $raw): string
    {
        $lower = strtolower($raw);
        if (str_contains($lower, 'allocation') || str_contains($raw, 'تخصيص')) {
            return 'No leave balance available for this type. Please contact HR to allocate days.';
        }
        if (str_contains($lower, 'overlap') || str_contains($raw, 'تداخل')) {
            return 'Leave dates overlap with an existing request.';
        }
        if (str_contains($lower, 'already') && str_contains($lower, 'checked')) {
            return 'You are already checked in.';
        }
        // Strip XML-RPC noise like "faultString:" prefixes if present
        if (preg_match('/faultString["\s:]+(.+)/i', $raw, $m)) {
            return trim($m[1]);
        }
        return $raw;
    }

    /**
     * Resolves the geofence for an employee: their assigned work location
     * (active + coordinates set) wins; otherwise the global .env office.
     * Returns ['lat' => ?float, 'lng' => ?float, 'radius' => int, 'name' => ?string].
     */
    private function resolveGeofence(?int $odooEmployeeId): array
    {
        if ($odooEmployeeId) {
            $emp = Employee::where('odoo_id', $odooEmployeeId)->first();
            if ($emp?->odoo_work_location_id) {
                $office = WorkLocation::where('odoo_id', $emp->odoo_work_location_id)
                    ->where('active', true)
                    ->first();
                if ($office?->hasGeofence()) {
                    return [
                        'lat'    => (float) $office->latitude,
                        'lng'    => (float) $office->longitude,
                        'radius' => $office->radius(),
                        'name'   => $office->name,
                    ];
                }
            }
        }

        $lat = config('attendance.office_lat');
        $lng = config('attendance.office_lng');

        return [
            'lat'    => $lat !== null ? (float) $lat : null,
            'lng'    => $lng !== null ? (float) $lng : null,
            'radius' => (int) config('attendance.geofence_radius'),
            'name'   => null,
        ];
    }

    /**
     * Returns a friendly error message if the given coordinates violate the
     * geofence policy, or null if the punch is allowed. Validates against the
     * employee's assigned office when one is configured.
     */
    private function geofenceError($lat, $lng, ?int $odooEmployeeId = null): ?string
    {
        if (!config('attendance.geofence_enforce')) {
            return null;
        }

        $fence = $this->resolveGeofence($odooEmployeeId);

        // Geofence not configured → nothing to enforce.
        if ($fence['lat'] === null || $fence['lng'] === null) {
            return null;
        }

        if ($lat === null || $lng === null) {
            return 'Location is required to check in. Please enable location and try again.';
        }

        $distance = $this->distanceMeters((float) $lat, (float) $lng, $fence['lat'], $fence['lng']);

        if ($distance > $fence['radius']) {
            return sprintf(
                'You are %d m away from the office. You must be within %d m to check in/out.',
                (int) round($distance),
                $fence['radius']
            );
        }

        return null;
    }

    /** Great-circle distance between two points in meters (Haversine). */
    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0; // meters
        $dLat  = deg2rad($lat2 - $lat1);
        $dLng  = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function formatAttendance(Attendance $a): array
    {
        return [
            'id'            => $a->id,
            'employee_name' => $a->employee_name,
            'check_in'      => $a->check_in?->toIso8601String(),
            'check_out'     => $a->check_out?->toIso8601String(),
            'worked_hours'  => (float) $a->worked_hours,
            'in_latitude'   => $a->in_latitude !== null ? (float) $a->in_latitude : null,
            'in_longitude'  => $a->in_longitude !== null ? (float) $a->in_longitude : null,
            'out_latitude'  => $a->out_latitude !== null ? (float) $a->out_latitude : null,
            'out_longitude' => $a->out_longitude !== null ? (float) $a->out_longitude : null,
        ];
    }

    private function formatPayslip(Payslip $p, bool $withLines = false): array
    {
        $data = [
            'id'              => $p->id,
            'number'          => $p->number,
            'employee_name'   => $p->employee_name,
            'date_from'       => $p->date_from?->toDateString(),
            'date_to'         => $p->date_to?->toDateString(),
            'basic_total'     => (float) $p->basic_total,
            'gross_total'     => (float) $p->gross_total,
            'net_total'       => (float) $p->net_total,
            'deduction_total' => (float) $p->deduction_total,
            'allowance_total' => (float) $p->allowance_total,
            'state'           => $p->state,
        ];

        if ($withLines) {
            $data['lines'] = $p->lines->map(fn($l) => [
                'code'          => $l->code,
                'name'          => $l->name,
                'category_name' => $l->category_name,
                'category_code' => $l->category_code,
                'total'         => (float) $l->total,
            ])->all();
        }

        return $data;
    }
}
