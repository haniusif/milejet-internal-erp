<?php

namespace App\Http\Controllers;

use App\Models\FinanceInvoice;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    /** Customer invoices — the Finance module home. */
    public function index(Request $request)
    {
        return $this->list($request, 'customer', 'finance.invoices');
    }

    /** Vendor bills. */
    public function bills(Request $request)
    {
        return $this->list($request, 'vendor', 'finance.bills');
    }

    /** Shared list builder for invoices and bills. */
    private function list(Request $request, string $scope, string $view)
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

        $invoices = $query->orderByDesc('invoice_date')->orderByDesc('odoo_id')
                          ->paginate(20)->withQueryString();

        // Summary over the whole scope (posted only), in the company currency.
        $base = FinanceInvoice::query()->{$scope}()->where('state', 'posted');
        $stats = [
            'count'     => (clone $base)->count(),
            'total'     => (float) (clone $base)->sum('amount_total'),
            'due'       => (float) (clone $base)->where('payment_state', '!=', 'paid')->sum('amount_residual'),
            'overdue'   => (clone $base)->where('payment_state', '!=', 'paid')
                                ->whereDate('invoice_date_due', '<', now())->count(),
        ];
        $currency = FinanceInvoice::query()->{$scope}()->value('currency') ?? 'SAR';

        return view($view, [
            'invoices' => $invoices,
            'stats'    => $stats,
            'currency' => $currency,
            'scope'    => $scope,
        ]);
    }

    public function show(int $id)
    {
        $invoice = FinanceInvoice::findOrFail($id);

        return view('finance.show', compact('invoice'));
    }
}
