<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Employee self-service requests (Odoo mj.hr.request via mj_hr_ess).
 * Employees raise & track own requests; HR (hr.view_all) approves/issues.
 */
class HrRequestController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    private const TYPES = ['certificate', 'equipment', 'uniform', 'vehicle', 'resignation', 'transfer', 'other'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = HrRequest::query();
        // Employees: own only. HR: all (or filter to "to approve").
        if (!$user->can('hr.view_all')) {
            $q->where('odoo_employee_id', $user->employeeRecord()?->odoo_id ?? -1);
        } elseif ($request->boolean('to_approve')) {
            $q->where('state', 'submitted');
        } elseif ($empId = $request->get('employee_id')) {
            $q->where('odoo_employee_id', (int) $empId);
        }
        if ($type = $request->get('type')) $q->where('request_type', $type);
        if ($state = $request->get('state')) $q->where('state', $state);

        $page = $q->orderByDesc('date_request')->orderByDesc('id')
            ->paginate(min((int) $request->get('per_page', 25), 100))
            ->withQueryString()->through(fn ($r) => $this->summary($r));

        return response()->json($page->toArray() + ['totals' => [
            'to_approve' => $user->can('hr.view_all') ? HrRequest::where('state', 'submitted')->count() : 0,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'request_type'    => ['required', Rule::in(self::TYPES)],
            'summary'         => 'nullable|string|max:255',
            'description'     => 'nullable|string|max:5000',
            'certificate_kind' => ['nullable', Rule::in(['salary', 'employment'])],
            'addressed_to'    => 'nullable|string|max:255',
            'target_department_id' => 'nullable|integer',
            'last_working_day' => 'nullable|date',
            'resign_reason'   => 'nullable|string|max:255',
            'item'            => 'nullable|string|max:255',
            'qty'             => 'nullable|integer|min:1',
            'employee_id'     => 'nullable|integer', // HR can raise for others (odoo id)
        ]);

        // Employees raise for themselves; HR may target another employee.
        $empOdooId = $user->can('hr.view_all') && !empty($data['employee_id'])
            ? (int) $data['employee_id']
            : ($user->employeeRecord()?->odoo_id ?? null);
        if (!$empOdooId) {
            return response()->json(['message' => __('No employee record linked to your account.')], 422);
        }

        $payload = array_filter([
            'employee_id'   => $empOdooId,
            'request_type'  => $data['request_type'],
            'summary'       => $data['summary'] ?? null,
            'description'   => $data['description'] ?? null,
            'certificate_kind' => $data['certificate_kind'] ?? null,
            'addressed_to'  => $data['addressed_to'] ?? null,
            'target_department_id' => !empty($data['target_department_id']) ? (int) $data['target_department_id'] : null,
            'last_working_day' => $data['last_working_day'] ?? null,
            'resign_reason' => $data['resign_reason'] ?? null,
            'item'          => $data['item'] ?? null,
            'qty'           => $data['qty'] ?? null,
        ], fn ($v) => $v !== null);

        try {
            $odooId = $this->odoo->create('mj.hr.request', $payload);
            $req = $this->sync->refreshHrRequest($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['id' => $req?->id, 'odoo_id' => $odooId]], 201);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'action_submit', ['draft'], false);
    }

    public function action(Request $request, int $id, string $action): JsonResponse
    {
        // approve/refuse/issue require HR; cancel allowed by owner (draft/submitted).
        $map = ['approve' => 'action_approve', 'refuse' => 'action_refuse',
                'issue' => 'action_issue', 'cancel' => 'action_cancel', 'reset' => 'action_reset'];
        abort_unless(isset($map[$action]), 404);
        $needsHr = in_array($action, ['approve', 'refuse', 'issue', 'reset'], true);
        return $this->transition($request, $id, $map[$action], null, $needsHr);
    }

    protected function transition(Request $request, int $id, string $method, ?array $fromStates, bool $needsHr): JsonResponse
    {
        $req = HrRequest::findOrFail($id);
        $user = $request->user();
        if ($needsHr && !$user->can('hr.view_all')) abort(403);
        if (!$needsHr && !$user->can('hr.view_all') && $req->odoo_employee_id !== ($user->employeeRecord()?->odoo_id ?? -1)) abort(403);
        if ($fromStates && !in_array($req->state, $fromStates, true)) {
            return response()->json(['message' => __('Not allowed in the current state.')], 422);
        }
        try {
            $this->odoo->executeKw('mj.hr.request', $method, [[$req->odoo_id]]);
            $req = $this->sync->refreshHrRequest($req->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => $this->summary($req)]);
    }

    public function certificate(Request $request, int $id)
    {
        $req = HrRequest::findOrFail($id);
        $user = $request->user();
        if (!$user->can('hr.view_all') && $req->odoo_employee_id !== ($user->employeeRecord()?->odoo_id ?? -1)) abort(403);
        try {
            $b64 = $this->odoo->useServiceAccount()->executeKw('mj.hr.request', 'render_certificate', [[$req->odoo_id]]);
        } catch (\Throwable) {
            abort(404);
        }
        $bytes = is_string($b64) ? base64_decode($b64, true) : false;
        if ($bytes === false) abort(404);
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($req->name ?: 'certificate') . '.pdf"',
        ]);
    }

    public function config(): JsonResponse
    {
        return response()->json(['data' => [
            'types'       => self::TYPES,
            'departments' => Department::orderBy('name')->get(['odoo_id', 'name']),
        ]]);
    }

    /** Surface hr_expense (installed, unused) — employee expense claims. */
    public function expenses(Request $request): JsonResponse
    {
        $user = $request->user();
        $domain = [];
        if (!$user->can('hr.view_all')) {
            $domain[] = ['employee_id', '=', $user->employeeRecord()?->odoo_id ?? -1];
        }
        try {
            $rows = $this->odoo->useServiceAccount()->searchRead('hr.expense', $domain,
                ['id', 'name', 'employee_id', 'total_amount', 'date', 'state'], 100, 0, 'date desc');
        } catch (\Throwable) {
            $rows = [];
        }
        return response()->json(['data' => array_map(fn ($e) => [
            'name' => $e['name'], 'employee' => OdooService::many2oneName($e['employee_id']),
            'amount' => (float) ($e['total_amount'] ?? 0), 'date' => $e['date'] ?: null, 'state' => $e['state'] ?? null,
        ], $rows)]);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date'   => 'nullable|date',
        ]);
        $empId = $request->user()->employeeRecord()?->odoo_id;
        if (!$empId) return response()->json(['message' => __('No employee record linked.')], 422);
        try {
            $this->odoo->useServiceAccount()->create('hr.expense', array_filter([
                'name' => $data['name'], 'employee_id' => $empId,
                'total_amount' => (float) $data['amount'], 'date' => $data['date'] ?? now()->toDateString(),
            ]));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['message' => 'created'], 201);
    }

    protected function summary(HrRequest $r): array
    {
        return [
            'id' => $r->id, 'name' => $r->name, 'employee_name' => $r->employee_name,
            'request_type' => $r->request_type, 'state' => $r->state,
            'date_request' => $r->date_request?->toDateString(), 'summary' => $r->summary,
            'description' => $r->description, 'approver' => $r->approver_name, 'manager_note' => $r->manager_note,
            'certificate_kind' => $r->certificate_kind, 'addressed_to' => $r->addressed_to,
            'has_certificate' => (bool) $r->has_certificate, 'target_department' => $r->target_department,
            'last_working_day' => $r->last_working_day?->toDateString(), 'resign_reason' => $r->resign_reason,
            'item' => $r->item, 'qty' => $r->qty,
        ];
    }
}
