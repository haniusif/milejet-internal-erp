<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\PayslipPayment;
use App\Models\SalaryAdjustment;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * HR "Actions": salary adjustments (penalties / deductions / rewards /
 * allowances) and payslip payments — Odoo models from the mj_hr_actions addon.
 * Reads come from the local cache; writes go to Odoo first, then refresh.
 */
class HrActionsController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    private const KINDS = ['penalty', 'deduction', 'reward', 'allowance'];

    // ─── Salary adjustments ─────────────────────────────────────────

    public function adjustments(Request $request): JsonResponse
    {
        $scope = function () use ($request) {
            $q = SalaryAdjustment::query();
            if ($kind = $request->get('kind')) {
                $q->where('kind', $kind);
            }
            if ($empId = $request->get('employee_id')) {
                $q->where('odoo_employee_id', (int) $empId);
            }
            return $q;
        };

        $query = $scope();
        if ($state = $request->get('state')) {
            $query->where('state', $state);
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $page = $query->orderByDesc('date')->orderByDesc('odoo_id')
            ->paginate(min((int) $request->get('per_page', 20), 100))
            ->withQueryString()->through(fn ($a) => $this->adjustmentSummary($a));

        return response()->json($page->toArray() + [
            'totals' => [
                'approved'        => (float) $scope()->where('state', 'approved')->sum('amount'),
                'approved_count'  => $scope()->where('state', 'approved')->count(),
                'draft_count'     => $scope()->where('state', 'draft')->count(),
            ],
        ]);
    }

    public function storeAdjustment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer', // odoo employee id
            'kind'        => ['required', Rule::in(self::KINDS)],
            'amount'      => 'required|numeric|min:0.01',
            'date'        => 'nullable|date',
            'reason'      => 'nullable|string|max:255',
            'approve'     => 'sometimes|boolean',
        ]);

        $payload = [
            'employee_id' => (int) $data['employee_id'],
            'kind'        => $data['kind'],
            'amount'      => round((float) $data['amount'], 2),
        ];
        if (!empty($data['date']))   $payload['date'] = $data['date'];
        if (!empty($data['reason'])) $payload['reason'] = $data['reason'];

        try {
            $odooId = $this->odoo->create('hr.salary.adjustment', $payload);
            if ($data['approve'] ?? true) {
                $this->odoo->executeKw('hr.salary.adjustment', 'action_approve', [[$odooId]]);
            }
            $adj = $this->sync->refreshSalaryAdjustment($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $adj?->id, 'odoo_id' => $odooId]], 201);
    }

    public function approveAdjustment(int $id): JsonResponse
    {
        return $this->adjustmentAction($id, 'action_approve');
    }

    public function cancelAdjustment(int $id): JsonResponse
    {
        return $this->adjustmentAction($id, 'action_cancel');
    }

    protected function adjustmentAction(int $id, string $method): JsonResponse
    {
        $adj = SalaryAdjustment::findOrFail($id);

        try {
            $this->odoo->executeKw('hr.salary.adjustment', $method, [[$adj->odoo_id]]);
            $this->sync->refreshSalaryAdjustment($adj->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $adj->id]]);
    }

    public function destroyAdjustment(int $id): JsonResponse
    {
        $adj = SalaryAdjustment::findOrFail($id);

        try {
            $this->odoo->unlink('hr.salary.adjustment', [$adj->odoo_id]);
            $adj->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    // ─── Payslip payments ───────────────────────────────────────────

    public function payments(int $payslipId): JsonResponse
    {
        $payslip = Payslip::findOrFail($payslipId);

        return response()->json([
            'data' => $payslip->payments->map(fn ($p) => $this->paymentSummary($p))->values(),
            'summary' => [
                'net_total'      => (float) $payslip->net_total,
                'amount_paid'    => (float) $payslip->amount_paid,
                'amount_due'     => (float) $payslip->amount_due,
                'payment_status' => $payslip->payment_status,
            ],
        ]);
    }

    public function storePayment(Request $request, int $payslipId): JsonResponse
    {
        $payslip = Payslip::findOrFail($payslipId);
        $data = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'date'      => 'nullable|date',
            'method'    => ['nullable', Rule::in(['bank', 'cash', 'cheque', 'other'])],
            'reference' => 'nullable|string|max:255',
            'note'      => 'nullable|string|max:255',
        ]);

        $payload = [
            'payslip_id' => $payslip->odoo_id,
            'amount'     => round((float) $data['amount'], 2),
        ];
        foreach (['date', 'method', 'reference', 'note'] as $f) {
            if (!empty($data[$f])) $payload[$f] = $data[$f];
        }

        try {
            $this->odoo->create('hr.payslip.payment', $payload);
            $this->sync->refreshPayslipPayments($payslip->odoo_id);
            $this->sync->refreshPayslip($payslip->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $payslip->id]], 201);
    }

    public function destroyPayment(int $id): JsonResponse
    {
        $payment = PayslipPayment::findOrFail($id);
        $payslipOdooId = $payment->odoo_payslip_id;

        try {
            $this->odoo->unlink('hr.payslip.payment', [$payment->odoo_id]);
            $payment->delete();
            $this->sync->refreshPayslipPayments($payslipOdooId);
            $this->sync->refreshPayslip($payslipOdooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    // ─── Serializers ────────────────────────────────────────────────

    protected function adjustmentSummary(SalaryAdjustment $a): array
    {
        return [
            'id'            => $a->id,
            'odoo_id'       => $a->odoo_id,
            'name'          => $a->name,
            'employee_name' => $a->employee_name,
            'odoo_employee_id' => $a->odoo_employee_id,
            'kind'          => $a->kind,
            'date'          => $a->date?->toDateString(),
            'amount'        => (float) $a->amount,
            'reason'        => $a->reason,
            'state'         => $a->state,
        ];
    }

    protected function paymentSummary(PayslipPayment $p): array
    {
        return [
            'id'        => $p->id,
            'date'      => $p->date?->toDateString(),
            'amount'    => (float) $p->amount,
            'method'    => $p->method,
            'reference' => $p->reference,
            'note'      => $p->note,
        ];
    }
}
