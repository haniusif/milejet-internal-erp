{{--
    Shared invoice/bill list — used by finance/invoices and finance/bills.
    Expects: $invoices (paginator), $stats, $currency, $scope, plus
    $heading / $kicker / $partnerLabel / $searchPlaceholder from the parent.
--}}
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Finance') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">
            {{ $heading }} <span class="text-slate-400 font-medium">({{ $invoices->total() }})</span>
        </h1>
    </div>
</div>

{{-- Summary --}}
@php
    $summary = [
        [__('Posted'),  number_format($stats['count']),               'text-slate-900 dark:text-slate-100'],
        [__('Total :cur', ['cur' => $currency]), number_format($stats['total'], 2),  'text-slate-900 dark:text-slate-100'],
        [__('Outstanding'), number_format($stats['due'], 2),          'text-amber-600 dark:text-amber-400'],
        [__('Overdue'), number_format($stats['overdue']),             $stats['overdue'] ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100'],
    ];
@endphp
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    @foreach ($summary as [$label, $value, $color])
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ $label }}</p>
            <p class="text-2xl font-bold tracking-tight tabular-nums leading-none {{ $color }}">{{ $value }}</p>
        </div>
    @endforeach
</div>

<form method="GET" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
    <div class="relative flex-1 min-w-[180px]">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             class="ico-sm absolute {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 text-slate-400">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ $searchPlaceholder }}"
               class="w-full h-9 {{ app()->getLocale() === 'ar' ? 'pe-9 ps-3' : 'ps-9 pe-3' }} border border-slate-200 dark:border-slate-700 rounded-md text-sm
                      bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                      focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:focus:ring-brand-900 focus:outline-none">
    </div>
    <select name="status" onchange="this.form.submit()"
            class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
        <option value="">{{ __('All statuses') }}</option>
        <option value="posted"  @selected(request('status')==='posted')>{{ __('Posted') }}</option>
        <option value="draft"   @selected(request('status')==='draft')>{{ __('Draft') }}</option>
        <option value="unpaid"  @selected(request('status')==='unpaid')>{{ __('Not paid') }}</option>
        <option value="overdue" @selected(request('status')==='overdue')>{{ __('Overdue') }}</option>
    </select>
    <button class="h-9 px-4 rounded-md bg-slate-900 dark:bg-slate-700 text-white text-sm">{{ __('Search') }}</button>
</form>

<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <th class="text-start font-medium px-4 py-3">{{ __('Number') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ $partnerLabel }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Date') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Due') }}</th>
                    <th class="text-end font-medium px-4 py-3">{{ __('Total') }}</th>
                    <th class="text-end font-medium px-4 py-3">{{ __('Due amount') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Status') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Payment') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($invoices as $inv)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition cursor-pointer"
                        onclick="window.location='{{ route('finance.show', $inv->id) }}'">
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            {{ $inv->name }}
                            @if ($inv->isRefund())
                                <span class="ms-1 text-[10px] uppercase tracking-wide text-rose-500">{{ __('Refund') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $inv->partner_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 tabular-nums text-xs">{{ $inv->invoice_date?->format('Y-m-d') ?: '—' }}</td>
                        <td class="px-4 py-3 tabular-nums text-xs {{ $inv->isOverdue() ? 'text-rose-600 dark:text-rose-400 font-medium' : 'text-slate-500 dark:text-slate-400' }}">
                            {{ $inv->invoice_date_due?->format('Y-m-d') ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-end tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($inv->amount_total, 2) }}</td>
                        <td class="px-4 py-3 text-end tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($inv->amount_residual, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $inv->stateColor() }}">{{ $inv->stateLabel() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $inv->paymentStateColor() }}">{{ $inv->paymentStateLabel() }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-12 text-sm text-slate-400 dark:text-slate-500">{{ __('Nothing here yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $invoices->links() }}</div>
