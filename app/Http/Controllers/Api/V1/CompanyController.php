<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Companies & branches (Odoo res.company — a branch is a company with a parent).
 * Reads for hr.view_all; writes gated by companies.manage.
 */
class CompanyController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    public function index(): JsonResponse
    {
        $headcount = Employee::where('active', true)
            ->whereNotNull('odoo_company_id')
            ->selectRaw('odoo_company_id, COUNT(*) AS c')
            ->groupBy('odoo_company_id')
            ->pluck('c', 'odoo_company_id');

        // Parents first, then branches — the UI indents children under their parent.
        $companies = Company::orderByRaw('odoo_parent_id IS NOT NULL')->orderBy('name')->get()
            ->map(fn ($c) => [
                'id'               => $c->id,
                'odoo_id'          => $c->odoo_id,
                'name'             => $c->name,
                'parent_odoo_id'   => $c->odoo_parent_id,
                'parent_name'      => $c->parent_name,
                'company_registry' => $c->company_registry,
                'vat'              => $c->vat,
                'phone'            => $c->phone,
                'email'            => $c->email,
                'city'             => $c->city,
                'country'          => $c->country_name,
                'active'           => $c->active,
                'employees'        => (int) ($headcount[$c->odoo_id] ?? 0),
            ]);

        return response()->json([
            'data'   => $companies,
            'totals' => [
                'companies'  => $companies->where('active', true)->whereNull('parent_odoo_id')->count(),
                'branches'   => $companies->where('active', true)->whereNotNull('parent_odoo_id')->count(),
                'assigned'   => (int) $headcount->sum(),
                'unassigned' => Employee::where('active', true)->whereNull('odoo_company_id')->count(),
            ],
        ]);
    }

    protected function rules(): array
    {
        return [
            'name'             => 'required|string|max:255',
            'parent_id'        => 'nullable|integer', // odoo id of the parent company → makes it a branch
            'company_registry' => 'nullable|string|max:64',
            'vat'              => 'nullable|string|max:64',
            'phone'            => 'nullable|string|max:64',
            'email'            => 'nullable|email|max:255',
            'city'             => 'nullable|string|max:128',
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $payload = array_filter($data, fn ($v) => $v !== null && $v !== '');
        if (isset($payload['parent_id'])) $payload['parent_id'] = (int) $payload['parent_id'];

        try {
            $odooId = $this->odoo->create('res.company', $payload);
            $company = $this->sync->refreshCompany($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $company?->id, 'odoo_id' => $odooId]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $data = $request->validate($this->rules());

        $payload = $data;
        $payload['parent_id'] = empty($payload['parent_id']) ? false : (int) $payload['parent_id'];
        if ($payload['parent_id'] === $company->odoo_id) {
            return response()->json(['message' => __('A company cannot be its own branch.')], 422);
        }

        try {
            $this->odoo->write('res.company', [$company->odoo_id], $payload);
            $this->sync->refreshCompany($company->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $company->id]]);
    }

    /** Archive — Odoo blocks deleting companies with data, archiving is the safe path. */
    public function archive(int $id): JsonResponse
    {
        return $this->setActive($id, false);
    }

    public function restore(int $id): JsonResponse
    {
        return $this->setActive($id, true);
    }

    protected function setActive(int $id, bool $active): JsonResponse
    {
        $company = Company::findOrFail($id);

        if (!$active && Employee::where('active', true)->where('odoo_company_id', $company->odoo_id)->exists()) {
            return response()->json(['message' => __('Reassign this company\'s employees before archiving it.')], 422);
        }

        try {
            $this->odoo->write('res.company', [$company->odoo_id], ['active' => $active]);
            $this->sync->refreshCompany($company->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $company->id]]);
    }
}
