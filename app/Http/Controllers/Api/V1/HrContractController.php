<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Employee;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Contract documents: PDF export, renewal and e-signature
 * (Odoo hr.contract extended by the mj_hr_contract addon).
 */
class HrContractController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    /** Owner employee or any contracts.view staffer. */
    private function canSee(Request $request, Contract $contract): bool
    {
        $user = $request->user();
        return $user->can('contracts.view')
            || $contract->odoo_employee_id === ($user->employeeRecord()?->odoo_id ?? -1);
    }

    public function pdf(Request $request, int $id)
    {
        $contract = Contract::findOrFail($id);
        abort_unless($this->canSee($request, $contract), 403);

        // Make sure the contract carries the employee's current terms before printing.
        $this->pushTerms($contract);

        try {
            // Service account: ownership is already enforced above, and a plain
            // employee's own Odoo user may lack hr.contract read rights.
            $b64 = $this->odoo->useServiceAccount()->executeKw('hr.contract', 'render_pdf', [[$contract->odoo_id]]);
        } catch (\Throwable) {
            abort(404);
        }
        $bytes = is_string($b64) ? base64_decode($b64, true) : false;
        if ($bytes === false) {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($contract->name ?: 'contract') . '.pdf"',
        ]);
    }

    public function renew(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);
        $data = $request->validate(['months' => 'nullable|integer|in:1,3,6,12,24']);

        try {
            $newId = $this->odoo->executeKw('hr.contract', 'action_renew',
                [[$contract->odoo_id]], ['months' => $data['months'] ?? false]);
            $this->sync->refreshContract($contract->odoo_id);     // old → close
            if (is_int($newId)) {
                $this->sync->refreshContract($newId);             // new contract
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['old_id' => $contract->id, 'new_odoo_id' => $newId]], 201);
    }

    public function sign(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);
        abort_unless($this->canSee($request, $contract), 403);

        $data = $request->validate([
            'signature' => 'nullable|string', // base64 PNG (data-URI accepted)
            'signer'    => 'nullable|string|max:255',
        ]);

        $signer = $data['signer']
            ?? $request->user()->employeeRecord()?->name
            ?? $request->user()->name;

        try {
            $this->odoo->useServiceAccount()->executeKw('hr.contract', 'mj_sign', [[$contract->odoo_id]], [
                'signature' => $data['signature'] ?? false,
                'signer'    => $signer,
            ]);
            $this->sync->refreshContract($contract->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $contract->id]]);
    }

    /** Mirror the employee's current employment terms onto the Odoo contract. */
    protected function pushTerms(Contract $contract): void
    {
        $emp = Employee::where('odoo_id', $contract->odoo_employee_id)->first();
        if (!$emp) {
            return;
        }
        $terms = array_filter([
            'mj_contract_type'   => $emp->contract_type ?: false,
            'mj_duration_months' => $emp->contract_duration_months !== null ? (int) $emp->contract_duration_months : false,
            'mj_notice_days'     => $emp->notice_period_days !== null ? (int) $emp->notice_period_days : false,
            'mj_probation_days'  => $emp->probation_period_days !== null ? (int) $emp->probation_period_days : false,
            'mj_auto_renewal'    => (bool) $emp->auto_renewal,
            'mj_work_schedule'   => $emp->work_schedule ?: false,
        ], fn ($v) => $v !== false);

        if ($terms) {
            try {
                $this->odoo->useServiceAccount()->write('hr.contract', [$contract->odoo_id], $terms);
            } catch (\Throwable) {
                // Non-fatal: print whatever the contract already has.
            }
        }
    }
}
