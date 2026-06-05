@extends('layouts.app')
@section('title', $invoice->name)

@section('content')
@php
    $isVendor = in_array($invoice->move_type, ['in_invoice', 'in_refund'], true);
    $backRoute = $isVendor ? 'finance.bills' : 'finance.invoices';
    $rows = [
        [__('Type'),        $isVendor ? __('Vendor bill') : __('Customer invoice')],
        [$isVendor ? __('Vendor') : __('Customer'), $invoice->partner_name ?: '—'],
        [__('Reference'),   $invoice->ref ?: '—'],
        [__('Journal'),     $invoice->journal_name ?: '—'],
        [__('Invoice date'), $invoice->invoice_date?->format('Y-m-d') ?: '—'],
        [__('Due date'),    $invoice->invoice_date_due?->format('Y-m-d') ?: '—'],
    ];
@endphp

<a href="{{ route($backRoute) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 mb-4">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
        @if (app()->getLocale() === 'ar') <path d="m9 18 6-6-6-6"/> @else <path d="m15 18-6-6 6-6"/> @endif
    </svg>
    {{ $isVendor ? __('Back to bills') : __('Back to invoices') }}
</a>

<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
            {{ $invoice->name }}
            @if ($invoice->isRefund())
                <span class="text-sm font-medium text-rose-500">{{ __('Refund') }}</span>
            @endif
        </h1>
        <div class="flex items-center gap-2 mt-2">
            <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $invoice->stateColor() }}">{{ $invoice->stateLabel() }}</span>
            <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $invoice->paymentStateColor() }}">{{ $invoice->paymentStateLabel() }}</span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    {{-- Details --}}
    <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ __('Details') }}</h2>
        <dl class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($rows as [$label, $value])
                <div class="flex items-center justify-between py-2.5">
                    <dt class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                    <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    {{-- Amounts --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ __('Amounts') }}</h2>
        <div class="space-y-3">
            <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Total') }}</p>
                <p class="text-2xl font-bold tracking-tight tabular-nums text-slate-900 dark:text-slate-100">
                    {{ number_format($invoice->amount_total, 2) }} <span class="text-sm font-medium text-slate-400">{{ $invoice->currency }}</span>
                </p>
            </div>
            <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Due amount') }}</p>
                <p class="text-xl font-semibold tabular-nums {{ $invoice->amount_residual > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ number_format($invoice->amount_residual, 2) }} <span class="text-sm font-medium text-slate-400">{{ $invoice->currency }}</span>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
