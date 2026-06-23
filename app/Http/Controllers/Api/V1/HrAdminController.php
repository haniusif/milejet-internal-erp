<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Payslip;
use App\Models\WorkLocation;
use App\Services\ExcelExport;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/**
 * HR admin/staff surface: everything route-gated (employees.write,
 * departments.write, payslips.create, …) — mirrors the web controllers.
 */
class HrAdminController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    // ─── Employees (employees.write / employees.delete) ─────

    /** Fields written to hr.employee in Odoo as-is. */
    private const ODOO_EMPLOYEE_FIELDS = [
        'name', 'work_email', 'work_phone', 'mobile_phone', 'job_title',
        'department_id', 'parent_id', 'work_location_id', 'company_id',
    ];

    /** Master-sheet columns kept on the local employees row (a subset is mirrored to Odoo via odooPersonalPayload). */
    private const LOCAL_EMPLOYEE_FIELDS = [
        'emp_code', 'birthday', 'family_status', 'nationality_code', 'nationality',
        'region', 'iqama_id', 'iqama_expiry_date', 'passport_id', 'passport_expiry_date',
        'license_expiry_date', 'cchi_card_type',
        'date_of_joining', 'contract_type', 'contract_end_date',
        'contract_duration_months', 'work_schedule', 'notice_period_days',
        'probation_period_days', 'auto_renewal',
        'total_salary', 'basic_salary',
        'allowance_house', 'allowance_rent', 'allowance_transport', 'allowance_car',
        'allowance_special', 'allowance_project', 'allowance_food', 'allowance_other',
        'ot_allowance', 'gosi_pm',
    ];

    protected function employeeRules(?int $ignoreId = null): array
    {
        return [
            'name'          => 'required|string|max:255',
            'work_email'    => 'nullable|email|max:255',
            'work_phone'    => 'nullable|string|max:64',
            'mobile_phone'  => 'nullable|string|max:64',
            'job_title'     => 'nullable|string|max:255',
            'department_id' => 'nullable|integer',
            'parent_id'     => 'nullable|integer',
            'work_location_id' => 'nullable|integer',
            'company_id'    => 'nullable|integer',
            // Personal / IDs (emp_code auto-generated on create when left empty)
            'emp_code'         => ['nullable', 'string', 'max:32',
                                   Rule::unique('employees', 'emp_code')->ignore($ignoreId)],
            'birthday'         => 'nullable|date',
            'family_status'    => 'nullable|string|in:S,M,W,D,C',
            'nationality_code' => 'nullable|string|max:8',
            'nationality'      => 'nullable|string|max:64',
            'region'           => 'nullable|string|max:64',
            'iqama_id'         => 'nullable|string|max:32',
            'iqama_expiry_date' => 'nullable|date',
            'passport_id'      => 'nullable|string|max:32',
            'passport_expiry_date' => 'nullable|date',
            'license_expiry_date'  => 'nullable|date',
            'cchi_card_type'   => 'nullable|string|max:64',
            // Employment
            'date_of_joining'   => 'nullable|date',
            'contract_type'     => 'nullable|string|max:64',
            'contract_end_date' => 'nullable|date|after_or_equal:date_of_joining',
            'contract_duration_months' => 'nullable|integer|in:1,6,12,24',
            'work_schedule'            => 'nullable|string|in:full_time,part_time,shifts,remote',
            'notice_period_days'       => 'nullable|integer|min:0|max:365',
            'probation_period_days'    => 'nullable|integer|min:0|max:365',
            'auto_renewal'             => 'nullable|boolean',
            // Salary (local master columns; wage of the Odoo contract = total_salary)
            'total_salary'        => 'nullable|numeric|min:0',
            'basic_salary'        => 'nullable|numeric|min:0',
            'allowance_house'     => 'nullable|numeric|min:0',
            'allowance_rent'      => 'nullable|numeric|min:0',
            'allowance_transport' => 'nullable|numeric|min:0',
            'allowance_car'       => 'nullable|numeric|min:0',
            'allowance_special'   => 'nullable|numeric|min:0',
            'allowance_project'   => 'nullable|numeric|min:0',
            'allowance_food'      => 'nullable|numeric|min:0',
            'allowance_other'     => 'nullable|numeric|min:0',
            'ot_allowance'        => 'nullable|numeric|min:0',
            'gosi_pm'             => 'nullable|numeric|min:0',
            'create_contract'     => 'sometimes|boolean',
            // Profile photo: base64 (raw or data-URI), written to Odoo image_1920.
            'image'               => 'nullable|string',
        ];
    }

    /**
     * Normalise an uploaded image to the raw base64 Odoo's image_1920 expects:
     * strips a "data:image/...;base64," prefix and surrounding whitespace.
     * Returns false to clear the field (Odoo falls back to the default avatar).
     */
    protected function normalizeImage(?string $value): string|false
    {
        if ($value === null || trim($value) === '') {
            return false;
        }
        if (preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,(.*)$/s', $value, $m)) {
            $value = $m[1];
        }
        return preg_replace('/\s+/', '', $value);
    }

    public function storeEmployee(Request $request): JsonResponse
    {
        $data = $request->validate($this->employeeRules());

        if (empty($data['emp_code'])) {
            $data['emp_code'] = $this->nextEmpCode();
        }

        $wantsContract = (bool) ($data['create_contract'] ?? false);
        if ($wantsContract && (empty($data['date_of_joining']) || empty($data['total_salary']))) {
            return response()->json([
                'message' => __('Creating a contract requires a joining date and a total salary.'),
            ], 422);
        }

        $payload = array_filter(
            array_intersect_key($data, array_flip(self::ODOO_EMPLOYEE_FIELDS)),
            fn ($v) => $v !== null && $v !== ''
        );
        foreach (['department_id', 'parent_id', 'work_location_id', 'company_id'] as $f) {
            if (isset($payload[$f])) $payload[$f] = (int) $payload[$f];
        }
        $payload += array_filter($this->odooPersonalPayload($data), fn ($v) => $v !== false);
        if (array_key_exists('image', $data) && ($image = $this->normalizeImage($data['image'])) !== false) {
            $payload['image_1920'] = $image;
        }

        try {
            $odooId = $this->odoo->create('hr.employee', $payload);
            $this->sync->refreshEmployee($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $employee = Employee::where('odoo_id', $odooId)->first();
        $employee?->update($this->localEmployeeColumns($data));

        $contractOdooId = null;
        $warning = null;
        if ($wantsContract && $employee) {
            try {
                $contractOdooId = $this->createInitialContract($employee, $data);
            } catch (Throwable $e) {
                $warning = __('Employee created, but the contract failed: :error', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'data'    => ['id' => $employee?->id, 'odoo_id' => $odooId, 'contract_odoo_id' => $contractOdooId],
            'warning' => $warning,
        ], 201);
    }

    public function updateEmployee(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $data = $request->validate($this->employeeRules($employee->id));

        $payload = array_intersect_key($data, array_flip(self::ODOO_EMPLOYEE_FIELDS));
        foreach (['department_id', 'parent_id', 'work_location_id'] as $f) {
            $payload[$f] = empty($payload[$f]) ? false : (int) $payload[$f];
        }
        // company_id: never clear via false — an Odoo employee must keep a company.
        if (!empty($payload['company_id'])) {
            $payload['company_id'] = (int) $payload['company_id'];
        } else {
            unset($payload['company_id']);
        }
        $payload += $this->odooPersonalPayload($data);
        // Only touch the photo when the client actually sent an image field
        // (empty string clears it; a missing key leaves the current photo intact).
        if (array_key_exists('image', $data)) {
            $payload['image_1920'] = $this->normalizeImage($data['image']);
        }

        try {
            $this->odoo->write('hr.employee', [$employee->odoo_id], $payload);
            $this->sync->refreshEmployee($employee->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $employee->update($this->localEmployeeColumns($data));

        return response()->json(['data' => ['id' => $employee->id]]);
    }

    /** res.country list for the nationality picker — cached, Odoo rarely changes it. */
    public function countries(): JsonResponse
    {
        $countries = cache()->remember('odoo.countries', now()->addDay(), function () {
            return $this->odoo->searchRead('res.country', [], ['id', 'code', 'name'], 0, 0, 'name asc');
        });

        return response()->json(['data' => $countries]);
    }

    // ─── Suspension / access management (employees.write) ───
    // Mirrors the recommended Odoo flows: archive employee, archive the
    // res.users login, or end the contract — all preserve history.

    /** Login-account status for the manage card (live Odoo lookup). */
    public function employeeAccess(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        try {
            $user = $this->odooUserFor($employee);

            return response()->json(['data' => [
                'has_user'    => $user !== null,
                'user_active' => $user['active'] ?? false,
                'login'       => $user['login'] ?? null,
            ]]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Option 1: archive (active=false) — keeps all history. Optionally also disables login. */
    public function archiveEmployee(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        try {
            $this->odoo->write('hr.employee', [$employee->odoo_id], ['active' => false]);
            if ($request->boolean('disable_login')) {
                $this->setOdooUserActive($employee, false);
            }
            $this->sync->refreshEmployee($employee->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $employee->id]]);
    }

    /** Unarchive — back to the active list. */
    public function restoreEmployee(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        try {
            $this->odoo->write('hr.employee', [$employee->odoo_id], ['active' => true]);
            $this->sync->refreshEmployee($employee->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $employee->id]]);
    }

    /** Option 2/4: enable or disable the res.users login (temporary suspension keeps the employee active). */
    public function setEmployeeAccess(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $data = $request->validate(['enabled' => 'required|boolean']);

        try {
            $this->setOdooUserActive($employee, (bool) $data['enabled']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $employee->id, 'enabled' => (bool) $data['enabled']]]);
    }

    /** Option 3: end the open contract (sets date_end; closes it when the date is past). */
    public function endContract(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $data = $request->validate(['date_end' => 'required|date']);

        $contract = Contract::where('odoo_employee_id', $employee->odoo_id)
            ->whereIn('state', ['open', 'pending', 'draft'])
            ->orderByRaw("CASE state WHEN 'open' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->first();
        if (!$contract) {
            return response()->json(['message' => __('No open contract found for this employee.')], 422);
        }

        $payload = ['date_end' => $data['date_end']];
        if ($data['date_end'] <= now()->toDateString()) {
            $payload['state'] = 'close';
        }

        try {
            $this->odoo->write('hr.contract', [$contract->odoo_id], $payload);
            $this->sync->refreshContract($contract->odoo_id);
            $employee->update(['contract_end_date' => $data['date_end']]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['contract_id' => $contract->id]]);
    }

    /** Extend an employee's probation by N days (updates the contract's trial period too). */
    public function extendProbation(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $data = $request->validate(['days' => 'required|integer|min:1|max:180']);

        $newDays = (int) $employee->probation_period_days + (int) $data['days'];
        $employee->update(['probation_period_days' => $newDays]);

        // Mirror onto the open contract so the printed terms stay in sync.
        $contract = Contract::where('odoo_employee_id', $employee->odoo_id)
            ->whereIn('state', ['open', 'pending', 'draft'])->first();
        if ($contract) {
            try {
                $this->odoo->write('hr.contract', [$contract->odoo_id], ['mj_probation_days' => $newDays]);
            } catch (\Throwable) {
                // local update already persisted
            }
        }

        return response()->json(['data' => ['id' => $employee->id, 'probation_period_days' => $newDays]]);
    }

    /** Per-employee attendance geofence bypass (local-only flag, read by the mobile punch API). */
    public function setGeofence(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $data = $request->validate(['exempt' => 'required|boolean']);

        $employee->update(['geofence_exempt' => (bool) $data['exempt']]);

        return response()->json(['data' => ['id' => $employee->id, 'exempt' => (bool) $data['exempt']]]);
    }

    /** Resolve the employee's res.users row (provisioning sets hr.employee.user_id). */
    protected function odooUserFor(Employee $employee): ?array
    {
        $rows = $this->odoo->read('hr.employee', [$employee->odoo_id], ['user_id']);
        $userId = OdooService::many2oneId($rows[0]['user_id'] ?? false);

        if (!$userId && $employee->work_email) {
            // Fallback for accounts created before user_id was linked.
            $found = $this->odoo->searchRead('res.users',
                [['login', '=', $employee->work_email], ['active', 'in', [true, false]]],
                ['id', 'login', 'active'], 1);
            return $found[0] ?? null;
        }
        if (!$userId) {
            return null;
        }

        $rows = $this->odoo->searchRead('res.users',
            [['id', '=', $userId], ['active', 'in', [true, false]]],
            ['id', 'login', 'active'], 1);

        return $rows[0] ?? null;
    }

    protected function setOdooUserActive(Employee $employee, bool $active): void
    {
        $user = $this->odooUserFor($employee);
        if (!$user) {
            throw new RuntimeException(__('This employee has no login account.'));
        }
        $this->odoo->write('res.users', [$user['id']], ['active' => $active]);
    }

    /** Personal fields Odoo also knows about — same mapping as the master-sheet import. */
    protected function odooPersonalPayload(array $data): array
    {
        $payload = [];
        if (array_key_exists('birthday', $data)) {
            $payload['birthday'] = $data['birthday'] ?: false;
        }
        if (array_key_exists('iqama_id', $data)) {
            $payload['identification_id'] = $data['iqama_id'] ?: false;
        }
        if (array_key_exists('passport_id', $data)) {
            $payload['passport_id'] = $data['passport_id'] ?: false;
        }
        if (!empty($data['family_status'])) {
            $marital = match (mb_strtoupper(trim($data['family_status']))) {
                'S' => 'single', 'M' => 'married', 'W' => 'widower',
                'D' => 'divorced', 'C' => 'cohabitant', default => null,
            };
            if ($marital) $payload['marital'] = $marital;
        }
        if (!empty($data['nationality_code']) || !empty($data['nationality'])) {
            $countryId = $this->lookupCountryId($data['nationality_code'] ?? '', $data['nationality'] ?? '');
            if ($countryId) $payload['country_id'] = $countryId;
        }
        return $payload;
    }

    /** Local-only columns to persist on the employees row (empties dropped — no clearing, same as text fields). */
    protected function localEmployeeColumns(array $data): array
    {
        return array_filter(
            array_intersect_key($data, array_flip(self::LOCAL_EMPLOYEE_FIELDS)),
            fn ($v) => $v !== null && $v !== ''
        );
    }

    /** Next sequential employee code, continuing the numeric master-sheet series (…, 1002, 1003). */
    protected function nextEmpCode(): string
    {
        $max = (int) Employee::whereRaw("emp_code REGEXP '^[0-9]+$'")
            ->selectRaw('MAX(CAST(emp_code AS UNSIGNED)) AS m')
            ->value('m');
        do {
            $code = (string) ++$max;
        } while (Employee::where('emp_code', $code)->exists());

        return $code;
    }

    /** Initial hr.contract in Odoo — wage = total salary, SA-STD structure (same as employees:create-contracts). */
    protected function createInitialContract(Employee $employee, array $data): int
    {
        $payload = [
            'name'        => 'Contract - ' . $employee->name,
            'employee_id' => $employee->odoo_id,
            'wage'        => round((float) $data['total_salary'], 2),
            'date_start'  => $data['date_of_joining'],
            'state'       => 'open',
            'struct_id'   => (int) config('odoo.payroll_struct_id', 1),
        ];
        if (!empty($data['contract_end_date'])) {
            $payload['date_end'] = $data['contract_end_date'];
        }
        // Carry the employment terms onto the contract so the printed PDF has them.
        $payload += $this->contractTermsPayload($data);

        $contractOdooId = $this->odoo->create('hr.contract', $payload);
        $this->sync->refreshContract($contractOdooId);
        $employee->update(['contract_status' => 'Active']);

        return $contractOdooId;
    }

    /** Employment-term fields mirrored onto the Odoo contract (mj_hr_contract). */
    protected function contractTermsPayload(array $data): array
    {
        $terms = [];
        if (!empty($data['contract_type']))            $terms['mj_contract_type'] = $data['contract_type'];
        if (isset($data['contract_duration_months']))  $terms['mj_duration_months'] = (int) $data['contract_duration_months'];
        if (isset($data['notice_period_days']))        $terms['mj_notice_days'] = (int) $data['notice_period_days'];
        if (isset($data['probation_period_days']))     $terms['mj_probation_days'] = (int) $data['probation_period_days'];
        if (isset($data['auto_renewal']))              $terms['mj_auto_renewal'] = (bool) $data['auto_renewal'];
        if (!empty($data['work_schedule']))            $terms['mj_work_schedule'] = $data['work_schedule'];
        return $terms;
    }

    private function lookupCountryId(string $code, string $name): ?int
    {
        $aliases = ['KSA' => 'SA', 'JOR' => 'JO', 'SD' => 'SD'];
        $code = strtoupper(trim($code));
        $iso  = $aliases[$code] ?? (strlen($code) === 2 ? $code : null);
        try {
            if ($iso) {
                $ids = $this->odoo->search('res.country', [['code', '=', $iso]], ['limit' => 1]);
                if (!empty($ids)) return $ids[0];
            }
            if (trim($name) !== '') {
                $ids = $this->odoo->search('res.country', [['name', 'ilike', trim($name)]], ['limit' => 1]);
                return $ids[0] ?? null;
            }
        } catch (Throwable) {
            // country is best-effort — never block employee creation on it
        }
        return null;
    }

    public function destroyEmployee(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        try {
            $this->odoo->unlink('hr.employee', [$employee->odoo_id]);
            $employee->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    /** Active employees tree for the org chart (hr.view_all). */
    public function orgChart(): JsonResponse
    {
        $employees = Employee::where('active', true)->orderBy('name')
            ->get(['id', 'odoo_id', 'emp_code', 'name', 'job_title', 'department_name', 'odoo_parent_id', 'image_small']);

        return response()->json([
            'data' => $employees->map(fn ($e) => [
                'id'         => $e->id,
                'odoo_id'    => $e->odoo_id,
                'emp_code'   => $e->emp_code,
                'name'       => $e->name,
                'job_title'  => $e->job_title,
                'department' => $e->department_name,
                'parent_odoo_id' => $e->odoo_parent_id,
                'avatar'     => $e->avatar_data_uri,
            ]),
        ]);
    }

    // ─── Departments (departments.write / .delete) ───────────

    public function departmentsFull(): JsonResponse
    {
        return response()->json([
            'data' => Department::orderBy('name')->get()->map(fn ($d) => [
                'id'             => $d->id,
                'odoo_id'        => $d->odoo_id,
                'name'           => $d->name,
                'parent_odoo_id' => $d->odoo_parent_id,
                'parent_name'    => $d->parent_name,
                'manager_odoo_id'=> $d->odoo_manager_id,
                'manager_name'   => $d->manager_name,
                'total_employee' => $d->total_employee,
            ]),
        ]);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'manager_id' => 'nullable|integer',
            'parent_id'  => 'nullable|integer',
        ]);

        $payload = array_filter($data, fn ($v) => $v !== null && $v !== '');
        foreach (['manager_id', 'parent_id'] as $f) {
            if (isset($payload[$f])) $payload[$f] = (int) $payload[$f];
        }

        try {
            $odooId = $this->odoo->create('hr.department', $payload);
            $this->sync->refreshDepartment($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['odoo_id' => $odooId]], 201);
    }

    public function updateDepartment(Request $request, int $id): JsonResponse
    {
        $department = Department::findOrFail($id);
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'manager_id' => 'nullable|integer',
            'parent_id'  => 'nullable|integer',
        ]);

        $payload = $data;
        foreach (['manager_id', 'parent_id'] as $f) {
            $payload[$f] = empty($payload[$f]) ? false : (int) $payload[$f];
        }

        try {
            $this->odoo->write('hr.department', [$department->odoo_id], $payload);
            $this->sync->refreshDepartment($department->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $department->id]]);
    }

    public function destroyDepartment(int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        try {
            $this->odoo->unlink('hr.department', [$department->odoo_id]);
            $department->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    // ─── Work locations (work_locations.write / .delete) ─────

    public function workLocations(): JsonResponse
    {
        $headcount = Employee::where('active', true)
            ->whereNotNull('odoo_work_location_id')
            ->selectRaw('odoo_work_location_id, COUNT(*) AS c')
            ->groupBy('odoo_work_location_id')
            ->pluck('c', 'odoo_work_location_id');

        return response()->json([
            'data' => WorkLocation::orderBy('name')->get()->map(fn ($w) => [
                'id'              => $w->id,
                'odoo_id'         => $w->odoo_id,
                'name'            => $w->name,
                'location_type'   => $w->location_type,
                'address_name'    => $w->address_name,
                'latitude'        => $w->latitude,
                'longitude'       => $w->longitude,
                'geofence_radius' => $w->geofence_radius,
                'active'          => (bool) $w->active,
                'employees'       => (int) ($headcount[$w->odoo_id] ?? 0),
            ]),
            'totals' => [
                'assigned'   => (int) $headcount->sum(),
                'unassigned' => Employee::where('active', true)->whereNull('odoo_work_location_id')->count(),
            ],
        ]);
    }

    protected function workLocationRules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'location_type'   => 'required|in:office,home,other',
            'latitude'        => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude'       => 'nullable|required_with:latitude|numeric|between:-180,180',
            'geofence_radius' => 'nullable|integer|min:10|max:100000',
        ];
    }

    public function storeWorkLocation(Request $request): JsonResponse
    {
        $data = $request->validate($this->workLocationRules());

        try {
            $odooId = $this->odoo->create('hr.work.location', [
                'name'          => $data['name'],
                'location_type' => $data['location_type'],
                'address_id'    => $this->companyPartnerId(),
            ]);
            $location = $this->sync->refreshWorkLocation($odooId);
            $location?->update($this->geoColumns($data));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['odoo_id' => $odooId]], 201);
    }

    public function updateWorkLocation(Request $request, int $id): JsonResponse
    {
        $location = WorkLocation::findOrFail($id);
        $data = $request->validate($this->workLocationRules());

        try {
            $this->odoo->write('hr.work.location', [$location->odoo_id], [
                'name'          => $data['name'],
                'location_type' => $data['location_type'],
            ]);
            $this->sync->refreshWorkLocation($location->odoo_id);
            $location->update($this->geoColumns($data));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $location->id]]);
    }

    public function destroyWorkLocation(int $id): JsonResponse
    {
        $location = WorkLocation::findOrFail($id);

        try {
            $this->odoo->unlink('hr.work.location', [$location->odoo_id]);
            $location->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    // ─── Contracts (contracts.view) ──────────────────────────

    /** Days before date_end an open contract counts as "expiring soon". */
    private const CONTRACT_EXPIRING_DAYS = 60;

    /** Pseudo-state: open & ends within the expiring window. */
    private function contractExpiring($q)
    {
        $today = now()->startOfDay();
        return $q->where('state', 'open')
            ->whereNotNull('date_end')
            ->whereBetween('date_end', [$today, $today->copy()->addDays(self::CONTRACT_EXPIRING_DAYS)]);
    }

    /** Pseudo-state: closed, or past its end date but never closed in Odoo. */
    private function contractExpired($q)
    {
        $today = now()->startOfDay();
        return $q->where(fn ($qq) => $qq
            ->where('state', 'close')
            ->orWhere(fn ($past) => $past->whereNotNull('date_end')->where('date_end', '<', $today)));
    }

    /** Contract query with the list endpoint's state/search filters applied. */
    private function contractsQuery(Request $request)
    {
        $query = Contract::query();

        if ($state = $request->get('state')) {
            match ($state) {
                'expiring' => $this->contractExpiring($query),
                'expired'  => $this->contractExpired($query),
                default    => $query->where('state', $state),
            };
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('odoo_id');
    }

    public function contracts(Request $request): JsonResponse
    {
        $expiring = fn ($q) => $this->contractExpiring($q);
        $expired  = fn ($q) => $this->contractExpired($q);

        $page = $this->contractsQuery($request)->paginate(min((int) $request->get('per_page', 20), 100))
            ->withQueryString()->through(fn ($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'employee_name' => $c->employee_name,
                'wage'          => $c->wage,
                'date_start'    => $c->date_start?->toDateString(),
                'date_end'      => $c->date_end?->toDateString(),
                'state'         => $c->state,
                'struct_name'   => $c->struct_name,
                'signed'        => (bool) $c->signed,
            ]);

        return response()->json($page->toArray() + [
            'totals' => [
                'count'      => Contract::count(),
                'open'       => Contract::where('state', 'open')->count(),
                'expiring'   => $expiring(Contract::query())->count(),
                'expired'    => $expired(Contract::query())->count(),
                'total_wage' => (float) Contract::where('state', 'open')->sum('wage'),
            ],
        ]);
    }

    /** Same dataset as the contracts list (current filters applied), as an .xlsx download. */
    public function exportContracts(Request $request)
    {
        if (in_array($lang = $request->get('lang'), ['ar', 'en'], true)) {
            app()->setLocale($lang);
        }

        $rows = $this->contractsQuery($request)->get()->map(fn ($c) => [
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

    // ─── Payslip creation (payslips.create / .delete) ────────

    /** Employees with an open contract — pickable for payslip creation. */
    public function payableEmployees(): JsonResponse
    {
        $employees = Employee::where('active', true)
            ->whereIn('odoo_id', Contract::where('state', 'open')->pluck('odoo_employee_id'))
            ->orderBy('name')->get(['id', 'odoo_id', 'name', 'job_title']);

        return response()->json(['data' => $employees]);
    }

    public function storePayslip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'integer',
            'date_from'      => 'required|date',
            'date_to'        => 'required|date|after_or_equal:date_from',
            'compute'        => 'nullable|boolean',
        ]);

        $compute = $request->boolean('compute', true);
        $created = [];
        $skipped = [];
        $failed  = [];

        $contracts = Contract::where('state', 'open')
            ->whereIn('odoo_employee_id', $data['employee_ids'])
            ->get()->keyBy('odoo_employee_id');

        foreach ($data['employee_ids'] as $empOdooId) {
            $empOdooId = (int) $empOdooId;
            $contract = $contracts->get($empOdooId);
            $emp = Employee::where('odoo_id', $empOdooId)->first();
            $label = $emp?->name ?? "employee_id={$empOdooId}";

            if (!$contract) {
                $skipped[] = ['employee' => $label, 'reason' => __('No active contract')];
                continue;
            }

            try {
                $payload = [
                    'employee_id' => $empOdooId,
                    'date_from'   => $data['date_from'],
                    'date_to'     => $data['date_to'],
                    'contract_id' => $contract->odoo_id,
                ];
                if ($contract->odoo_struct_id) {
                    $payload['struct_id'] = $contract->odoo_struct_id;
                }
                $odooId = $this->odoo->create('hr.payslip', $payload);

                if ($compute) {
                    $this->odoo->executeKw('hr.payslip', 'compute_sheet', [[$odooId]]);
                }
                $this->sync->refreshPayslip($odooId);
                $created[] = ['employee' => $label, 'odoo_id' => $odooId];
            } catch (RuntimeException $e) {
                $failed[] = ['employee' => $label, 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'failed'  => $failed,
        ], empty($created) && !empty($failed) ? 422 : 201);
    }

    public function computePayslip(int $id): JsonResponse
    {
        $payslip = Payslip::findOrFail($id);

        try {
            $this->odoo->executeKw('hr.payslip', 'compute_sheet', [[$payslip->odoo_id]]);
            $this->sync->refreshPayslip($payslip->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'computed']);
    }

    public function destroyPayslip(int $id): JsonResponse
    {
        $payslip = Payslip::findOrFail($id);

        try {
            if (in_array($payslip->state, ['verify', 'done'])) {
                $this->odoo->executeKw('hr.payslip', 'action_payslip_cancel', [[$payslip->odoo_id]]);
            }
            $this->odoo->unlink('hr.payslip', [$payslip->odoo_id]);
            $payslip->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    // ─── Leave attachments + delete ──────────────────────────

    /** Attachment metadata for a page of leaves: ?ids=1,2,3 (local leave ids). */
    public function leaveAttachments(Request $request): JsonResponse
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->get('ids', ''))));
        if (empty($ids)) {
            return response()->json(['data' => []]);
        }

        $leaves = Leave::whereIn('id', $ids)->get();

        // Plain employees only get attachments on their own leaves.
        $user = $request->user();
        if (!$user->can('hr.view_all')) {
            $own = $user->employeeRecord()?->odoo_id ?? -1;
            $leaves = $leaves->where('odoo_employee_id', $own);
        }

        $odooIds = $leaves->pluck('odoo_id')->filter()->values()->all();
        if (empty($odooIds)) {
            return response()->json(['data' => []]);
        }

        try {
            $rows = $this->odoo->useServiceAccount()->searchRead(
                'ir.attachment',
                [['res_model', '=', 'hr.leave'], ['res_id', 'in', $odooIds]],
                ['id', 'name', 'mimetype', 'res_id']
            );
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['data' => []]);
        }

        $byLeaveOdooId = [];
        foreach ($rows as $r) {
            $resId = is_array($r['res_id'] ?? null) ? ($r['res_id'][0] ?? null) : ($r['res_id'] ?? null);
            if ($resId !== null) {
                $byLeaveOdooId[$resId][] = ['id' => $r['id'], 'name' => $r['name'], 'mimetype' => $r['mimetype']];
            }
        }

        // Re-key by local leave id for the SPA.
        $data = [];
        foreach ($leaves as $leave) {
            $data[$leave->id] = $byLeaveOdooId[$leave->odoo_id] ?? [];
        }

        return response()->json(['data' => $data]);
    }

    /** Streams a leave attachment (same ownership rule as the web route). */
    public function leaveAttachment(Request $request, int $id)
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

        $user = $request->user();
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

    public function destroyLeave(int $id): JsonResponse
    {
        $leave = Leave::findOrFail($id);

        try {
            $this->odoo->unlink('hr.leave', [$leave->odoo_id]);
            $leave->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    // ─── Attendance delete (role:admin,hr_manager) ───────────

    public function destroyAttendance(int $id): JsonResponse
    {
        $attendance = Attendance::findOrFail($id);

        try {
            $this->odoo->unlink('hr.attendance', [$attendance->odoo_id]);
            $attendance->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    // ─── Odoo sync (sync.run) ────────────────────────────────

    public function sync(Request $request, SyncService $sync): JsonResponse
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
                'loans'       => $sync->syncLoans(),
                'payslips'    => $sync->syncPayslips(),
                'recruitment' => $sync->syncRecruitment(),
                'crm'         => $sync->syncCrm(),
                'fleet'       => $sync->syncFleet(),
                'finance'     => $sync->syncFinance(),
                default       => $sync->syncAll(),
            };
        } catch (\Throwable $e) {
            return response()->json(['message' => __('Sync failed: :error', ['error' => $e->getMessage()])], 422);
        }

        return response()->json(['message' => 'synced']);
    }

    // ─── Helpers (mirrors WorkLocationController) ────────────

    private function geoColumns(array $data): array
    {
        return [
            'latitude'        => $data['latitude'] ?? null,
            'longitude'       => $data['longitude'] ?? null,
            'geofence_radius' => $data['geofence_radius'] ?? null,
        ];
    }

    private function companyPartnerId(): int
    {
        $rows = $this->odoo->searchRead('res.company', [], ['partner_id'], 1, 0, 'id asc');
        $partnerId = OdooService::many2oneId($rows[0]['partner_id'] ?? null);
        if (!$partnerId) {
            throw new RuntimeException('Could not resolve the company address in Odoo.');
        }
        return $partnerId;
    }
}
