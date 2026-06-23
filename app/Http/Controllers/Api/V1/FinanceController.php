<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FinanceInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    /** Customer invoices. */
    public function invoices(Request $request): JsonResponse
    {
        return $this->list($request, 'customer');
    }

    /** Vendor bills. */
    public function bills(Request $request): JsonResponse
    {
        return $this->list($request, 'vendor');
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->invoiceSummary(FinanceInvoice::findOrFail($id))]);
    }

    protected function list(Request $request, string $scope): JsonResponse
    {
        $query = FinanceInvoice::query()->{$scope}();

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('partner_name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%");
            });
        }
        match ($request->get('status')) {
            'draft'   => $query->where('state', 'draft'),
            'posted'  => $query->where('state', 'posted'),
            'unpaid'  => $query->where('state', 'posted')->where('payment_state', '!=', 'paid'),
            'overdue' => $query->where('state', 'posted')->where('payment_state', '!=', 'paid')
                               ->whereDate('invoice_date_due', '<', now()),
            default   => null,
        };

        $page = $query->orderByDesc('invoice_date')->orderByDesc('odoo_id')
            ->paginate(min((int) $request->get('per_page', 20), 100))
            ->withQueryString()->through(fn ($i) => $this->invoiceSummary($i));

        $base = FinanceInvoice::query()->{$scope}()->where('state', 'posted');

        return response()->json($page->toArray() + [
            'stats' => [
                'count'   => (clone $base)->count(),
                'total'   => (float) (clone $base)->sum('amount_total'),
                'due'     => (float) (clone $base)->where('payment_state', '!=', 'paid')->sum('amount_residual'),
                'overdue' => (clone $base)->where('payment_state', '!=', 'paid')
                                  ->whereDate('invoice_date_due', '<', now())->count(),
            ],
            'currency' => FinanceInvoice::query()->{$scope}()->value('currency') ?? 'SAR',
        ]);
    }

    protected function invoiceSummary(FinanceInvoice $i): array
    {
        return [
            'id'              => $i->id,
            'odoo_id'         => $i->odoo_id,
            'move_type'       => $i->move_type,
            'name'            => $i->name,
            'ref'             => $i->ref,
            'partner_name'    => $i->partner_name,
            'invoice_date'    => $i->invoice_date,
            'invoice_date_due'=> $i->invoice_date_due,
            'amount_total'    => $i->amount_total,
            'amount_residual' => $i->amount_residual,
            'currency'        => $i->currency,
            'state'           => $i->state,
            'payment_state'   => $i->payment_state,
            'journal'         => $i->journal_name,
        ];
    }
}
