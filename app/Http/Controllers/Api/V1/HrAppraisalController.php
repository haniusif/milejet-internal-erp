<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrAppraisal;
use App\Models\HrRecognition;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Performance appraisals (Odoo mj.hr.appraisal via mj_hr_performance).
 * Employees see & self-assess own reviews; HR (hr.view_all) runs cycles,
 * rates as manager, finalizes and grants rewards (payslips.create).
 */
class HrAppraisalController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    private function ownOdooId(Request $request): int
    {
        return $request->user()->employeeRecord()?->odoo_id ?? -1;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = HrAppraisal::query();
        if (!$user->can('hr.view_all')) {
            $q->where('odoo_employee_id', $this->ownOdooId($request));
        } elseif ($empId = $request->get('employee_id')) {
            $q->where('odoo_employee_id', (int) $empId);
        }
        if ($state = $request->get('state')) $q->where('state', $state);
        if ($period = $request->get('period')) $q->where('period', $period);

        $page = $q->orderByDesc('date_to')->orderByDesc('id')
            ->paginate(min((int) $request->get('per_page', 25), 100))
            ->withQueryString()->through(fn ($a) => $this->summary($a));

        return response()->json($page->toArray());
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $a = HrAppraisal::with('lines')->findOrFail($id);
        $this->authorizeView($request, $a);
        $data = $this->summary($a) + [
            'summary_text' => $a->summary,
            'reward_odoo_id' => $a->reward_odoo_id,
            'lines' => $a->lines->sortBy('sequence')->values()->map(fn ($l) => [
                'id' => $l->id, 'odoo_id' => $l->odoo_id, 'name' => $l->name,
                'category' => $l->category, 'skill_name' => $l->skill_name,
                'weight' => $l->weight, 'target' => $l->target,
                'auto_value' => $l->auto_value, 'is_auto' => $l->is_auto,
                'self_rating' => $l->self_rating, 'manager_rating' => $l->manager_rating,
                'score' => $l->score,
            ]),
        ];
        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => 'nullable|integer',
            'period'      => 'nullable|string|max:32',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
        ]);
        // Employees create own; HR may target another employee.
        $empOdooId = $user->can('hr.view_all') && !empty($data['employee_id'])
            ? (int) $data['employee_id']
            : ($user->employeeRecord()?->odoo_id ?? null);
        if (!$empOdooId) {
            return response()->json(['message' => __('No employee record linked to your account.')], 422);
        }
        $payload = array_filter([
            'employee_id' => $empOdooId,
            'period'      => $data['period'] ?? null,
            'date_from'   => $data['date_from'] ?? null,
            'date_to'     => $data['date_to'] ?? null,
        ], fn ($v) => $v !== null);

        try {
            $odooId = $this->odoo->create('mj.hr.appraisal', $payload);
            $a = $this->sync->refreshAppraisal($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['id' => $a?->id, 'odoo_id' => $odooId]], 201);
    }

    public function open(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'action_open', ['draft'], true);
    }

    public function action(Request $request, int $id, string $action): JsonResponse
    {
        $map = ['submit' => 'action_submit', 'finalize' => 'action_finalize',
                'cancel' => 'action_cancel', 'reset' => 'action_reset'];
        abort_unless(isset($map[$action]), 404);
        // submit is the employee's self-assessment hand-off; the rest are HR.
        $needsHr = $action !== 'submit';
        $fromStates = match ($action) {
            'submit' => ['self_assessment'],
            'finalize' => ['manager_review'],
            default => null,
        };
        return $this->transition($request, $id, $map[$action], $fromStates, $needsHr);
    }

    /** Set ratings on lines: employees set self_rating (self_assessment); HR sets manager_rating (manager_review). */
    public function updateLines(Request $request, int $id): JsonResponse
    {
        $a = HrAppraisal::findOrFail($id);
        $user = $request->user();
        $isHr = $user->can('hr.view_all');
        if (!$isHr && $a->odoo_employee_id !== $this->ownOdooId($request)) abort(403);

        $data = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.odoo_id' => 'required|integer',
            'lines.*.rating' => ['nullable', Rule::in(['1', '2', '3', '4', '5'])],
            'summary' => 'nullable|string|max:5000',
        ]);

        if ($isHr) {
            if ($a->state !== 'manager_review') {
                return response()->json(['message' => __('Manager ratings are set during manager review.')], 422);
            }
            $field = 'manager_rating';
        } else {
            if ($a->state !== 'self_assessment') {
                return response()->json(['message' => __('You can self-assess only during self-assessment.')], 422);
            }
            $field = 'self_rating';
        }

        // Only touch lines that belong to this appraisal.
        $ownLineIds = $a->lines()->pluck('odoo_id')->all();
        try {
            foreach ($data['lines'] as $line) {
                if (!in_array((int) $line['odoo_id'], $ownLineIds, true)) continue;
                $this->odoo->executeKw('mj.hr.appraisal.line', 'write',
                    [[(int) $line['odoo_id']], [$field => $line['rating'] ?: false]]);
            }
            if ($isHr && array_key_exists('summary', $data)) {
                $this->odoo->executeKw('mj.hr.appraisal', 'write', [[$a->odoo_id], ['summary' => $data['summary'] ?: false]]);
            }
            $this->sync->refreshAppraisal($a->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return $this->show($request, $id);
    }

    public function reward(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->can('payslips.create')) abort(403);
        $a = HrAppraisal::findOrFail($id);
        $data = $request->validate(['amount' => 'required|numeric|min:1']);
        try {
            $this->odoo->useServiceAccount()->executeKw('mj.hr.appraisal', 'action_grant_reward',
                [[$a->odoo_id]], ['context' => ['reward_amount' => (float) $data['amount']]]);
            $this->sync->refreshAppraisal($a->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return $this->show($request, $id);
    }

    protected function transition(Request $request, int $id, string $method, ?array $fromStates, bool $needsHr): JsonResponse
    {
        $a = HrAppraisal::findOrFail($id);
        $user = $request->user();
        if ($needsHr && !$user->can('hr.view_all')) abort(403);
        if (!$needsHr && !$user->can('hr.view_all') && $a->odoo_employee_id !== $this->ownOdooId($request)) abort(403);
        if ($fromStates && !in_array($a->state, $fromStates, true)) {
            return response()->json(['message' => __('Not allowed in the current state.')], 422);
        }
        try {
            $this->odoo->executeKw('mj.hr.appraisal', $method, [[$a->odoo_id]]);
            $this->sync->refreshAppraisal($a->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return $this->show($request, $id);
    }

    // --- Recognition wall ---

    public function recognitions(Request $request): JsonResponse
    {
        $q = HrRecognition::query();
        if ($empId = $request->get('employee_id')) $q->where('odoo_employee_id', (int) $empId);
        $rows = $q->orderByDesc('date')->orderByDesc('id')->limit(100)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'employee_name' => $r->employee_name, 'from_name' => $r->from_name,
                'badge' => $r->badge, 'message' => $r->message, 'date' => $r->date?->toIso8601String(),
            ]);
        return response()->json(['data' => $rows]);
    }

    public function storeRecognition(Request $request): JsonResponse
    {
        if (!$request->user()->can('hr.view_all')) abort(403);
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'badge' => ['nullable', Rule::in(['kudos', 'star', 'team'])],
            'message' => 'required|string|max:255',
        ]);
        try {
            $odooId = $this->odoo->create('mj.hr.recognition', array_filter([
                'employee_id' => (int) $data['employee_id'],
                'badge' => $data['badge'] ?? 'kudos',
                'message' => $data['message'],
            ]));
            $this->sync->refreshRecognition($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['message' => 'created'], 201);
    }

    // --- helpers ---

    protected function authorizeView(Request $request, HrAppraisal $a): void
    {
        $user = $request->user();
        if (!$user->can('hr.view_all') && $a->odoo_employee_id !== $this->ownOdooId($request)) abort(403);
    }

    protected function summary(HrAppraisal $a): array
    {
        return [
            'id' => $a->id, 'name' => $a->name, 'employee_name' => $a->employee_name,
            'odoo_employee_id' => $a->odoo_employee_id,
            'period' => $a->period, 'state' => $a->state,
            'date_from' => $a->date_from?->toDateString(), 'date_to' => $a->date_to?->toDateString(),
            'reviewer_name' => $a->reviewer_name, 'overall_rating' => round($a->overall_rating, 1),
            'has_reward' => (bool) $a->reward_odoo_id,
        ];
    }
}
