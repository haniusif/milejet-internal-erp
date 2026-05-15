@extends('layouts.app')
@section('title', __('Payslips'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400">{{ __('Payroll') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">{{ __('Payslips') }}</h1>
    </div>
    @can('payslips.create')
    <a href="{{ route('payslips.create') }}"
       class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition shadow-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        {{ __('Create payslip') }}
    </a>
    @endcan
</div>

{{-- Summary cards --}}
<div class="grid grid-cols-3 gap-3 mb-5">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs text-slate-500 mb-1">{{ __('Total payslips') }}</p>
        <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $totals['count'] }}</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs text-slate-500 mb-1">{{ __("This month's payslips") }}</p>
        <p class="text-2xl font-bold tracking-tight text-brand-700 tabular-nums">{{ $totals['this_month'] }}</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xs text-slate-500 mb-1">{{ __('Total net (cumulative)') }}</p>
        <p class="text-2xl font-bold tracking-tight text-emerald-700 tabular-nums">
            {{ number_format($totals['net_total'], 2) }} <span class="text-[10px] text-slate-400">{{ __('SAR') }}</span>
        </p>
    </div>
</div>

<form method="GET" class="bg-white border border-slate-200 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
    <select name="employee_id" class="h-9 px-3 border border-slate-200 rounded-md text-sm bg-white">
        <option value="">{{ __('All employees') }}</option>
        @foreach ($employees as $emp)
            <option value="{{ $emp->odoo_id }}" @selected(request('employee_id') == $emp->odoo_id)>{{ $emp->name }}</option>
        @endforeach
    </select>
    <input type="month" name="month" value="{{ request('month') }}" class="h-9 px-3 border border-slate-200 rounded-md text-sm">
    <select name="state" class="h-9 px-3 border border-slate-200 rounded-md text-sm bg-white">
        <option value="">{{ __('All statuses') }}</option>
        <option value="draft"  @selected(request('state')=='draft')>{{ __('Payslip state: draft') }}</option>
        <option value="verify" @selected(request('state')=='verify')>{{ __('Payslip state: verify') }}</option>
        <option value="done"   @selected(request('state')=='done')>{{ __('Payslip state: done') }}</option>
        <option value="cancel" @selected(request('state')=='cancel')>{{ __('Payslip state: cancel') }}</option>
    </select>
    <button class="h-9 px-4 bg-slate-900 text-white text-sm font-medium rounded-md hover:bg-slate-800">{{ __('Filter') }}</button>
</form>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-4 py-3 text-start font-medium">{{ __('Reference') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Employee') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Period') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Basic') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Gross') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Deductions') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Net') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($payslips as $p)
            <tr class="hover:bg-slate-50/60 transition">
                <td class="px-4 py-3 text-xs text-slate-500 font-mono">{{ $p->number ?? '—' }}</td>
                <td class="px-4 py-3 font-medium text-slate-900">{{ $p->employee_name }}</td>
                <td class="px-4 py-3 text-xs text-slate-500 tabular-nums">
                    {{ $p->date_from?->format('Y-m-d') }} → {{ $p->date_to?->format('Y-m-d') }}
                </td>
                <td class="px-4 py-3 text-slate-700 tabular-nums">{{ number_format($p->basic_total, 2) }}</td>
                <td class="px-4 py-3 text-slate-900 font-medium tabular-nums">{{ number_format($p->gross_total, 2) }}</td>
                <td class="px-4 py-3 text-rose-600 tabular-nums">{{ number_format($p->deduction_total, 2) }}</td>
                <td class="px-4 py-3 font-bold text-emerald-700 tabular-nums">{{ number_format($p->net_total, 2) }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full {{ $p->stateColor() }}">
                        {{ $p->stateLabel() }}
                    </span>
                </td>
                <td class="px-4 py-3 text-end">
                    <a href="{{ route('payslips.show', $p->id) }}"
                       class="inline-flex items-center gap-1 text-xs text-brand-600 hover:text-brand-700 hover:underline">
                        {{ __('View') }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                            @if (app()->getLocale() === 'ar')
                                <path d="m15 18-6-6 6-6"/>
                            @else
                                <path d="m9 18 6-6-6-6"/>
                            @endif
                        </svg>
                    </a>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="px-4 py-12 text-center">
                <p class="text-slate-400 mb-2">{{ __('No payslips yet') }}</p>
                @can('payslips.create')
                <a href="{{ route('payslips.create') }}" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:text-brand-700 hover:underline">
                    {{ __('Create the first payslip') }}
                </a>
                @endcan
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $payslips->links() }}</div>
@endsection
