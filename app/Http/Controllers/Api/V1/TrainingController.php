<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingNeed;
use App\Models\TrainingSession;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Training & development (Odoo mj_hr_training).
 * Employees browse the catalog, self-enroll, and view completions/certs;
 * HR (hr.view_all) defines courses, schedules sessions, marks attendance/
 * completion (which grants hr.employee.skill), and manages training needs.
 */
class TrainingController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    private function ownOdooId(Request $request): int
    {
        return $request->user()->employeeRecord()?->odoo_id ?? -1;
    }

    // --- Courses ---

    public function courses(Request $request): JsonResponse
    {
        $q = TrainingCourse::query()->where('active', true);
        if ($cat = $request->get('category')) $q->where('category', $cat);
        $rows = $q->orderBy('code')->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id, 'odoo_id' => $c->odoo_id, 'name' => $c->name, 'code' => $c->code,
            'category' => $c->category, 'description' => $c->description,
            'duration_hours' => $c->duration_hours, 'is_mandatory' => $c->is_mandatory,
            'validity_months' => $c->validity_months, 'pass_mark' => $c->pass_mark,
            'skill_names' => $c->skill_names, 'session_count' => $c->session_count,
        ]);
        return response()->json(['data' => $rows]);
    }

    /** hr.skill catalog for the course form's skill picker. */
    public function skills(): JsonResponse
    {
        try {
            $rows = $this->odoo->useServiceAccount()->searchRead('hr.skill', [],
                ['id', 'name', 'skill_type_id'], 500, 0, 'name');
        } catch (\Throwable) {
            $rows = [];
        }
        return response()->json(['data' => array_map(fn ($s) => [
            'id' => $s['id'], 'name' => $s['name'],
            'skill_type' => OdooService::many2oneName($s['skill_type_id'] ?? false),
        ], $rows)]);
    }

    public function storeCourse(Request $request): JsonResponse
    {
        if (!$request->user()->can('hr.view_all')) abort(403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:32',
            'category' => ['nullable', Rule::in(['onboarding', 'safety', 'compliance', 'skills', 'soft'])],
            'description' => 'nullable|string|max:5000',
            'duration_hours' => 'nullable|numeric|min:0',
            'is_mandatory' => 'boolean',
            'validity_months' => 'nullable|integer|min:0',
            'pass_mark' => 'nullable|integer|min:0|max:100',
            'skill_ids' => 'nullable|array',
            'skill_ids.*' => 'integer',
        ]);
        $payload = array_filter([
            'name' => $data['name'], 'code' => $data['code'] ?? null,
            'category' => $data['category'] ?? 'skills', 'description' => $data['description'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'is_mandatory' => $data['is_mandatory'] ?? false,
            'validity_months' => $data['validity_months'] ?? null,
            'pass_mark' => $data['pass_mark'] ?? null,
        ], fn ($v) => $v !== null);
        if (!empty($data['skill_ids'])) $payload['skill_ids'] = [[6, 0, $data['skill_ids']]];
        try {
            $odooId = $this->odoo->create('mj.hr.course', $payload);
            $this->sync->syncTrainingCourses();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['odoo_id' => $odooId]], 201);
    }

    // --- Sessions ---

    public function sessions(Request $request): JsonResponse
    {
        $q = TrainingSession::query();
        if ($request->boolean('open')) $q->whereIn('state', ['draft', 'confirmed', 'in_progress']);
        if ($courseId = $request->get('course_id')) $q->where('odoo_course_id', (int) $courseId);
        $rows = $q->orderByDesc('date_start')->orderByDesc('id')->limit(200)->get()->map(fn ($s) => $this->sessionSummary($s));
        return response()->json(['data' => $rows]);
    }

    public function showSession(Request $request, int $id): JsonResponse
    {
        $s = TrainingSession::with('enrollments')->findOrFail($id);
        $isHr = $request->user()->can('hr.view_all');
        $data = $this->sessionSummary($s) + [
            'enrollments' => $s->enrollments->map(fn ($e) => $this->enrollmentSummary($e))->values(),
        ];
        // Employees see only their own enrollment on a session.
        if (!$isHr) {
            $own = $this->ownOdooId($request);
            $data['enrollments'] = collect($data['enrollments'])->where('odoo_employee_id', $own)->values();
        }
        return response()->json(['data' => $data]);
    }

    public function storeSession(Request $request): JsonResponse
    {
        if (!$request->user()->can('hr.view_all')) abort(403);
        $data = $request->validate([
            'course_id' => 'required|integer',
            'mode' => ['nullable', Rule::in(['in_person', 'online', 'self'])],
            'location' => 'nullable|string|max:255',
            'trainer_external' => 'nullable|string|max:255',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'capacity' => 'nullable|integer|min:0',
        ]);
        $payload = array_filter([
            'course_id' => (int) $data['course_id'],
            'mode' => $data['mode'] ?? 'in_person', 'location' => $data['location'] ?? null,
            'trainer_external' => $data['trainer_external'] ?? null,
            'date_start' => $data['date_start'] ?? null, 'date_end' => $data['date_end'] ?? null,
            'capacity' => $data['capacity'] ?? null,
        ], fn ($v) => $v !== null);
        try {
            $odooId = $this->odoo->create('mj.hr.training.session', $payload);
            $s = $this->sync->refreshTrainingSession($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['id' => $s?->id, 'odoo_id' => $odooId]], 201);
    }

    public function sessionAction(Request $request, int $id, string $action): JsonResponse
    {
        if (!$request->user()->can('hr.view_all')) abort(403);
        $map = ['confirm' => 'action_confirm', 'start' => 'action_start',
                'close' => 'action_close', 'cancel' => 'action_cancel', 'reset' => 'action_reset'];
        abort_unless(isset($map[$action]), 404);
        $s = TrainingSession::findOrFail($id);
        try {
            $this->odoo->executeKw('mj.hr.training.session', $map[$action], [[$s->odoo_id]]);
            $this->sync->refreshTrainingSession($s->odoo_id);
            $this->sync->syncTrainingEnrollments();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return $this->showSession($request, $id);
    }

    // --- Enrollments ---

    public function enroll(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'session_id' => 'required|integer',   // odoo id
            'employee_id' => 'nullable|integer',  // HR may nominate
        ]);
        $empOdooId = $user->can('hr.view_all') && !empty($data['employee_id'])
            ? (int) $data['employee_id']
            : ($user->employeeRecord()?->odoo_id ?? null);
        if (!$empOdooId) {
            return response()->json(['message' => __('No employee record linked to your account.')], 422);
        }
        try {
            $odooId = $this->odoo->create('mj.hr.training.enrollment', [
                'session_id' => (int) $data['session_id'], 'employee_id' => $empOdooId,
            ]);
            $this->sync->refreshTrainingEnrollment($odooId);
            $this->sync->refreshTrainingSession((int) $data['session_id']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['odoo_id' => $odooId]], 201);
    }

    public function enrollmentAction(Request $request, int $id, string $action): JsonResponse
    {
        $user = $request->user();
        $e = TrainingEnrollment::findOrFail($id);
        // Employees may only cancel their own enrollment; the rest are HR.
        $isOwn = $e->odoo_employee_id === $this->ownOdooId($request);
        $hrActions = ['attend', 'complete', 'fail', 'no_show'];
        if (in_array($action, $hrActions, true) && !$user->can('hr.view_all')) abort(403);
        if ($action === 'cancel' && !$user->can('hr.view_all') && !$isOwn) abort(403);

        $map = ['attend' => 'action_attend', 'complete' => 'action_complete', 'fail' => 'action_fail',
                'no_show' => 'action_no_show', 'cancel' => 'action_cancel', 'reset' => 'action_reset'];
        abort_unless(isset($map[$action]), 404);

        try {
            if ($action === 'complete' && $request->has('score')) {
                $score = (int) $request->validate(['score' => 'nullable|integer|min:0|max:100'])['score'];
                $this->odoo->executeKw('mj.hr.training.enrollment', 'write', [[$e->odoo_id], ['score' => $score]]);
            }
            $this->odoo->executeKw('mj.hr.training.enrollment', $map[$action], [[$e->odoo_id]]);
            $this->sync->refreshTrainingEnrollment($e->odoo_id);
            $this->sync->refreshTrainingSession($e->odoo_session_id);
            $this->sync->syncTrainingNeeds();
        } catch (RuntimeException $ex) {
            return response()->json(['message' => $ex->getMessage()], 422);
        }
        return response()->json(['data' => $this->enrollmentSummary($e->fresh())]);
    }

    public function certificate(Request $request, int $id)
    {
        $e = TrainingEnrollment::findOrFail($id);
        $user = $request->user();
        if (!$user->can('hr.view_all') && $e->odoo_employee_id !== $this->ownOdooId($request)) abort(403);
        try {
            $b64 = $this->odoo->useServiceAccount()->executeKw('mj.hr.training.enrollment', 'render_certificate', [[$e->odoo_id]]);
        } catch (\Throwable) {
            abort(404);
        }
        $bytes = is_string($b64) ? base64_decode($b64, true) : false;
        if ($bytes === false) abort(404);
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($e->session_name ?: 'certificate') . '.pdf"',
        ]);
    }

    // --- My training ---

    public function myTraining(Request $request): JsonResponse
    {
        $own = $this->ownOdooId($request);
        $rows = TrainingEnrollment::where('odoo_employee_id', $own)
            ->orderByDesc('id')->get()->map(fn ($e) => $this->enrollmentSummary($e));
        // certs expiring within 60 days
        $expiring = $rows->filter(fn ($e) => $e['expiry_date'] && $e['state'] === 'completed'
            && $e['expiry_date'] <= now()->addDays(60)->toDateString())->values();
        return response()->json(['data' => ['enrollments' => $rows, 'expiring' => $expiring]]);
    }

    // --- Needs ---

    public function needs(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = TrainingNeed::query();
        if (!$user->can('hr.view_all')) {
            $q->where('odoo_employee_id', $this->ownOdooId($request));
        } elseif ($empId = $request->get('employee_id')) {
            $q->where('odoo_employee_id', (int) $empId);
        }
        if ($request->boolean('open_only', true)) $q->where('state', '!=', 'closed');
        $rows = $q->orderByDesc('id')->get()->map(fn ($n) => [
            'id' => $n->id, 'odoo_id' => $n->odoo_id, 'employee_name' => $n->employee_name,
            'odoo_employee_id' => $n->odoo_employee_id, 'skill_name' => $n->skill_name,
            'source' => $n->source, 'state' => $n->state, 'note' => $n->note,
        ]);
        return response()->json(['data' => $rows]);
    }

    public function needAction(Request $request, int $id, string $action): JsonResponse
    {
        if (!$request->user()->can('hr.view_all')) abort(403);
        $map = ['plan' => 'action_plan', 'close' => 'action_close'];
        abort_unless(isset($map[$action]), 404);
        $n = TrainingNeed::findOrFail($id);
        try {
            $this->odoo->executeKw('mj.hr.training.need', $map[$action], [[$n->odoo_id]]);
            $this->sync->syncTrainingNeeds();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['message' => 'ok']);
    }

    // --- helpers ---

    protected function sessionSummary(TrainingSession $s): array
    {
        return [
            'id' => $s->id, 'odoo_id' => $s->odoo_id, 'name' => $s->name,
            'odoo_course_id' => $s->odoo_course_id, 'course_name' => $s->course_name,
            'trainer_name' => $s->trainer_name, 'mode' => $s->mode, 'location' => $s->location,
            'date_start' => $s->date_start?->toIso8601String(), 'date_end' => $s->date_end?->toIso8601String(),
            'capacity' => $s->capacity, 'seats_taken' => $s->seats_taken, 'seats_left' => $s->seats_left,
            'state' => $s->state,
        ];
    }

    protected function enrollmentSummary(TrainingEnrollment $e): array
    {
        return [
            'id' => $e->id, 'odoo_id' => $e->odoo_id, 'session_name' => $e->session_name,
            'odoo_session_id' => $e->odoo_session_id, 'course_name' => $e->course_name,
            'odoo_employee_id' => $e->odoo_employee_id, 'employee_name' => $e->employee_name,
            'state' => $e->state, 'score' => $e->score,
            'completion_date' => $e->completion_date?->toDateString(),
            'expiry_date' => $e->expiry_date?->toDateString(),
            'has_certificate' => $e->has_certificate, 'feedback_rating' => $e->feedback_rating,
        ];
    }
}
