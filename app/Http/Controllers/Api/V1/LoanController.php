<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Loan;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Employee loans (Odoo hr.loan via the mj_loan addon).
 * Staff with loans.view see all; plain employees only their own (read-only).
 * Writes (loans.manage) go to Odoo first, then refresh the local cache.
 */
class LoanController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $scope = function () use ($user, $request) {
            $q = Loan::query();
            if (!$user->can('loans.view')) {
                $q->where('odoo_employee_id', $user->employeeRecord()?->odoo_id ?? -1);
            } elseif ($empId = $request->get('employee_id')) {
                $q->where('odoo_employee_id', $empId);
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

        $page = $query->with('lines')->orderByDesc('odoo_id')
            ->paginate(min((int) $request->get('per_page', 20), 100))
            ->withQueryString()->through(fn ($l) => $this->loanSummary($l));

        return response()->json($page->toArray() + [
            'totals' => [
                'active'      => $scope()->where('state', 'active')->count(),
                'outstanding' => (float) $scope()->where('state', 'active')->sum('balance'),
                'repaid'      => (float) $scope()->whereIn('state', ['active', 'paid'])->sum('repaid_amount'),
                'paid'        => $scope()->where('state', 'paid')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer', // odoo employee id
            'amount'      => 'required|numeric|min:1',
            'installment' => 'nullable|numeric|min:0',
            'date'        => 'nullable|date',
            'reason'      => 'nullable|string|max:255',
            'approve'     => 'sometimes|boolean',
        ]);

        $payload = [
            'employee_id' => (int) $data['employee_id'],
            'amount'      => round((float) $data['amount'], 2),
        ];
        if (!empty($data['installment'])) $payload['installment'] = round((float) $data['installment'], 2);
        if (!empty($data['date']))        $payload['date'] = $data['date'];
        if (!empty($data['reason']))      $payload['reason'] = $data['reason'];

        try {
            $odooId = $this->odoo->create('hr.loan', $payload);
            if ($data['approve'] ?? true) {
                $this->odoo->executeKw('hr.loan', 'action_approve', [[$odooId]]);
            }
            $loan = $this->sync->refreshLoan($odooId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $loan?->id, 'odoo_id' => $odooId]], 201);
    }

    public function approve(int $id): JsonResponse
    {
        return $this->action($id, 'action_approve');
    }

    public function cancel(int $id): JsonResponse
    {
        return $this->action($id, 'action_cancel');
    }

    protected function action(int $id, string $method): JsonResponse
    {
        $loan = Loan::findOrFail($id);

        try {
            $this->odoo->executeKw('hr.loan', $method, [[$loan->odoo_id]]);
            $this->sync->refreshLoan($loan->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $loan->id]]);
    }

    public function storeRepayment(Request $request, int $id): JsonResponse
    {
        $loan = Loan::findOrFail($id);
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date'   => 'nullable|date',
            'note'   => 'nullable|string|max:255',
        ]);

        if ($loan->state !== 'active') {
            return response()->json(['message' => __('Repayments are only allowed on active loans.')], 422);
        }
        if ((float) $data['amount'] > (float) $loan->balance) {
            return response()->json(['message' => __('Repayment exceeds the remaining balance (:balance).', ['balance' => $loan->balance])], 422);
        }

        $payload = ['loan_id' => $loan->odoo_id, 'amount' => round((float) $data['amount'], 2)];
        if (!empty($data['date'])) $payload['date'] = $data['date'];
        if (!empty($data['note'])) $payload['note'] = $data['note'];

        try {
            $this->odoo->create('hr.loan.line', $payload);
            $this->sync->refreshLoan($loan->odoo_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $loan->id]], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $loan = Loan::findOrFail($id);

        if (!in_array($loan->state, ['draft', 'cancel'], true)) {
            return response()->json(['message' => __('Only draft or cancelled loans can be deleted.')], 422);
        }

        try {
            $this->odoo->unlink('hr.loan', [$loan->odoo_id]);
            $loan->lines()->delete();
            $loan->delete();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'deleted']);
    }

    /** Active employees for the new-loan picker. */
    public function employees(): JsonResponse
    {
        return response()->json([
            'data' => Employee::where('active', true)->orderBy('name')
                ->get(['id', 'odoo_id', 'name', 'emp_code']),
        ]);
    }

    protected function loanSummary(Loan $l): array
    {
        return [
            'id'            => $l->id,
            'odoo_id'       => $l->odoo_id,
            'name'          => $l->name,
            'employee_name' => $l->employee_name,
            'date'          => $l->date?->toDateString(),
            'amount'        => (float) $l->amount,
            'installment'   => (float) $l->installment,
            'reason'        => $l->reason,
            'state'         => $l->state,
            'repaid_amount' => (float) $l->repaid_amount,
            'balance'       => (float) $l->balance,
            'lines'         => $l->lines->sortByDesc('date')->values()->map(fn ($ln) => [
                'id'     => $ln->id,
                'date'   => $ln->date?->toDateString(),
                'amount' => (float) $ln->amount,
                'note'   => $ln->note,
            ]),
        ];
    }
}
