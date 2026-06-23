<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\JobPosition;
use App\Models\RecruitmentStage;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RecruitmentController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    public function jobs(Request $request): JsonResponse
    {
        $query = JobPosition::where('active', true);

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($deptId = $request->get('department_id')) {
            $query->where('odoo_department_id', $deptId);
        }

        $page = $query->withCount(['applicants as open_applicants_count' => fn ($q) => $q->where('active', true)])
            ->orderBy('name')->paginate(min((int) $request->get('per_page', 20), 100))
            ->withQueryString()->through(fn ($j) => [
                'id'              => $j->id,
                'odoo_id'         => $j->odoo_id,
                'name'            => $j->name,
                'department'      => $j->department_name,
                'openings'        => $j->no_of_recruitment,
                'applicants'      => $j->open_applicants_count,
                'recruiter'       => $j->recruiter_name,
            ]);

        $hiredStageIds = RecruitmentStage::where('hired_stage', true)->pluck('odoo_id');

        return response()->json($page->toArray() + [
            'totals' => [
                'jobs'       => JobPosition::where('active', true)->count(),
                'openings'   => (int) JobPosition::where('active', true)->sum('no_of_recruitment'),
                'applicants' => Applicant::where('active', true)->count(),
                'hired'      => Applicant::where('active', true)->whereIn('odoo_stage_id', $hiredStageIds)->count(),
                'refused'    => Applicant::where('active', false)->count(),
            ],
        ]);
    }

    public function applicants(Request $request): JsonResponse
    {
        $query = Applicant::query();

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('partner_name', 'like', "%{$search}%")
                  ->orWhere('email_from', 'like', "%{$search}%")
                  ->orWhere('job_name', 'like', "%{$search}%");
            });
        }
        if ($jobId = $request->get('job_id')) {
            $query->where('odoo_job_id', $jobId);
        }
        if ($stageId = $request->get('stage_id')) {
            $query->where('odoo_stage_id', $stageId);
        }
        match ($request->get('status')) {
            'refused' => $query->where('active', false),
            'hired'   => $query->where('active', true)
                ->whereIn('odoo_stage_id', RecruitmentStage::where('hired_stage', true)->pluck('odoo_id')),
            'all'     => null,
            default   => $query->where('active', true),
        };

        $page = $query->orderByDesc('odoo_create_date')
            ->paginate(min((int) $request->get('per_page', 20), 200))
            ->withQueryString()->through(fn ($a) => $this->applicantSummary($a));

        return response()->json($page->toArray() + [
            'stages' => RecruitmentStage::orderBy('sequence')->get()->map(fn ($s) => [
                'odoo_id' => $s->odoo_id, 'name' => $s->name, 'sequence' => $s->sequence, 'hired' => (bool) $s->hired_stage,
            ]),
            'jobs' => JobPosition::where('active', true)->orderBy('name')
                ->get(['odoo_id', 'name']),
        ]);
    }

    public function storeApplicant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'partner_name'    => 'required|string|max:255',
            'email_from'      => 'nullable|email|max:255',
            'partner_phone'   => 'nullable|string|max:64',
            'job_id'          => 'required|integer|exists:job_positions,odoo_id',
            'salary_expected' => 'nullable|numeric|min:0',
            'description'     => 'nullable|string|max:5000',
        ]);

        $job = JobPosition::where('odoo_id', $data['job_id'])->first();

        try {
            $this->odoo->create('hr.applicant', array_filter([
                'partner_name'    => $data['partner_name'],
                'name'            => $data['partner_name'].' - '.$job->name,
                'email_from'      => $data['email_from'] ?? null,
                'partner_phone'   => $data['partner_phone'] ?? null,
                'job_id'          => (int) $data['job_id'],
                'department_id'   => $job->odoo_department_id,
                'salary_expected' => $data['salary_expected'] ?? null,
                'description'     => $data['description'] ?? null,
            ]));
            $this->sync->syncRecruitment();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'created'], 201);
    }

    public function moveStage(Request $request, int $id): JsonResponse
    {
        $applicant = Applicant::findOrFail($id);
        $data = $request->validate(['stage_id' => 'required|integer|exists:recruitment_stages,odoo_id']);

        try {
            $this->odoo->write('hr.applicant', [$applicant->odoo_id], ['stage_id' => (int) $data['stage_id']]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $stage = RecruitmentStage::where('odoo_id', $data['stage_id'])->first();
        $applicant->update(['odoo_stage_id' => $stage->odoo_id, 'stage_name' => $stage->name, 'synced_at' => now()]);

        return response()->json(['data' => $this->applicantSummary($applicant->fresh())]);
    }

    public function refuse(int $id): JsonResponse
    {
        $applicant = Applicant::findOrFail($id);

        try {
            $this->odoo->write('hr.applicant', [$applicant->odoo_id], ['active' => false]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $applicant->update(['active' => false, 'synced_at' => now()]);

        return response()->json(['data' => $this->applicantSummary($applicant->fresh())]);
    }

    public function restore(int $id): JsonResponse
    {
        $applicant = Applicant::findOrFail($id);

        try {
            $this->odoo->write('hr.applicant', [$applicant->odoo_id], ['active' => true]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $applicant->update(['active' => true, 'synced_at' => now()]);

        return response()->json(['data' => $this->applicantSummary($applicant->fresh())]);
    }

    protected function applicantSummary(Applicant $a): array
    {
        return [
            'id'              => $a->id,
            'odoo_id'         => $a->odoo_id,
            'name'            => $a->partner_name,
            'email'           => $a->email_from,
            'phone'           => $a->partner_phone,
            'job_id'          => $a->odoo_job_id,
            'job_name'        => $a->job_name,
            'stage_id'        => $a->odoo_stage_id,
            'stage_name'      => $a->stage_name,
            'department'      => $a->department_name,
            'salary_expected' => $a->salary_expected,
            'salary_proposed' => $a->salary_proposed,
            'active'          => (bool) $a->active,
            'created_at'      => $a->odoo_create_date,
        ];
    }
}
