<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\JobPosition;
use App\Models\RecruitmentStage;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use RuntimeException;

class RecruitmentController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    /** Open job positions with applicant counts. */
    public function jobs(Request $request)
    {
        $query = JobPosition::where('active', true);

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($deptId = $request->get('department_id')) {
            $query->where('odoo_department_id', $deptId);
        }

        $jobs = $query->withCount(['applicants as open_applicants_count' => fn ($q) => $q->where('active', true)])
            ->orderBy('name')->paginate(20)->withQueryString();

        $departments = JobPosition::where('active', true)
            ->whereNotNull('odoo_department_id')
            ->select('odoo_department_id', 'department_name')
            ->distinct()->orderBy('department_name')->get();

        return view('recruitment.jobs', compact('jobs', 'departments'));
    }

    /** Applicant pipeline. */
    public function applicants(Request $request)
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
            'all'     => null,
            default   => $query->where('active', true),
        };

        $applicants = $query->with('stage')
            ->orderByDesc('odoo_create_date')->paginate(20)->withQueryString();

        $stages = RecruitmentStage::orderBy('sequence')->get();
        $jobs   = JobPosition::where('active', true)->orderBy('name')->get();

        return view('recruitment.applicants', compact('applicants', 'stages', 'jobs'));
    }

    public function createApplicant()
    {
        $jobs = JobPosition::where('active', true)->orderBy('name')->get();

        return view('recruitment.create', compact('jobs'));
    }

    public function storeApplicant(Request $request)
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
            $odooId = $this->odoo->create('hr.applicant', array_filter([
                'partner_name'    => $data['partner_name'],
                'name'            => $data['partner_name'].' - '.$job->name, // subject line
                'email_from'      => $data['email_from'] ?? null,
                'partner_phone'   => $data['partner_phone'] ?? null,
                'job_id'          => (int) $data['job_id'],
                'department_id'   => $job->odoo_department_id,
                'salary_expected' => $data['salary_expected'] ?? null,
                'description'     => $data['description'] ?? null,
            ]));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => __('Could not create the applicant in Odoo: :error', ['error' => $e->getMessage()])]);
        }

        $this->sync->syncRecruitment();

        return redirect()->route('recruitment.applicants')
            ->with('status', __('✅ Applicant ":name" added', ['name' => $data['partner_name']]));
    }

    /** Move an applicant to another pipeline stage. */
    public function moveStage(Request $request, int $id)
    {
        $applicant = Applicant::findOrFail($id);
        $data = $request->validate([
            'stage_id' => 'required|integer|exists:recruitment_stages,odoo_id',
        ]);

        try {
            $this->odoo->write('hr.applicant', [$applicant->odoo_id], ['stage_id' => (int) $data['stage_id']]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not update the stage: :error', ['error' => $e->getMessage()])]);
        }

        $stage = RecruitmentStage::where('odoo_id', $data['stage_id'])->first();
        $applicant->update([
            'odoo_stage_id' => $stage->odoo_id,
            'stage_name'    => $stage->name,
            'synced_at'     => now(),
        ]);

        return back()->with('status', __('✅ :name moved to ":stage"', ['name' => $applicant->partner_name, 'stage' => $stage->name]));
    }

    /** Refuse = archive in Odoo. */
    public function refuse(int $id)
    {
        $applicant = Applicant::findOrFail($id);

        try {
            $this->odoo->write('hr.applicant', [$applicant->odoo_id], ['active' => false]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not refuse the applicant: :error', ['error' => $e->getMessage()])]);
        }

        $applicant->update(['active' => false, 'synced_at' => now()]);

        return back()->with('status', __('Applicant ":name" refused', ['name' => $applicant->partner_name]));
    }

    /** Restore a refused applicant back into the pipeline. */
    public function restore(int $id)
    {
        $applicant = Applicant::findOrFail($id);

        try {
            $this->odoo->write('hr.applicant', [$applicant->odoo_id], ['active' => true]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not restore the applicant: :error', ['error' => $e->getMessage()])]);
        }

        $applicant->update(['active' => true, 'synced_at' => now()]);

        return back()->with('status', __('✅ Applicant ":name" restored', ['name' => $applicant->partner_name]));
    }
}
