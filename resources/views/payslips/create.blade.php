@extends('layouts.app')
@section('title', __('Create payslips'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <a href="{{ route('payslips.index') }}" class="text-xs text-slate-500 hover:text-slate-700">← {{ __('Back to payslips') }}</a>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">{{ __('Create payslips') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Select one or more employees to generate payslips for the same period.') }}</p>
    </div>
</div>

@if ($errors->any())
<div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl p-3 mb-4">
    <ul class="list-disc ms-5 space-y-0.5">
        @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

@if (session('bulk_result'))
    @php $r = session('bulk_result'); @endphp
    @if (!empty($r['failed']) || !empty($r['skipped']))
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 text-sm text-amber-800">
        @if (!empty($r['skipped']))
            <p class="font-medium mb-1">{{ __('Skipped:') }}</p>
            <ul class="list-disc ms-5 mb-2">
                @foreach ($r['skipped'] as $row)
                    <li>{{ $row[0] }} — {{ $row[1] }}</li>
                @endforeach
            </ul>
        @endif
        @if (!empty($r['failed']))
            <p class="font-medium mb-1">{{ __('Failed:') }}</p>
            <ul class="list-disc ms-5">
                @foreach ($r['failed'] as $row)
                    <li>{{ $row[0] }} — {{ $row[1] }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    @endif
@endif

<form method="POST" action="{{ route('payslips.store') }}" id="bulk-form" class="space-y-4">
    @csrf

    {{-- Period + compute toggle --}}
    <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
            {{ __('Payroll period') }}
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('From date') }}</label>
                <input type="date" name="date_from" value="{{ old('date_from', $default_from) }}" required
                       class="w-full h-10 px-3 border border-slate-200 rounded-md text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('To date') }}</label>
                <input type="date" name="date_to" value="{{ old('date_to', $default_to) }}" required
                       class="w-full h-10 px-3 border border-slate-200 rounded-md text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 focus:outline-none">
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 h-10">
                    <input type="hidden" name="compute" value="0">
                    <input type="checkbox" name="compute" value="1" checked
                           class="w-4 h-4 rounded text-brand-600 focus:ring-brand-500">
                    {{ __('Compute lines immediately') }}
                </label>
            </div>
        </div>
    </div>

    {{-- Employee selector --}}
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200 flex flex-wrap items-center gap-3">
            <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                {{ __('Select employees') }}
                <span id="selected-count" class="text-xs font-medium px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 ring-1 ring-brand-200">0</span>
            </h2>
            <div class="ms-auto flex flex-wrap items-center gap-2">
                <input type="search" id="emp-filter" placeholder="{{ __('Filter by name, code or job…') }}"
                       class="h-9 px-3 border border-slate-200 rounded-md text-sm w-64 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 focus:outline-none">
                <button type="button" id="select-all-btn"
                        class="h-9 px-3 text-xs font-medium border border-slate-200 rounded-md hover:bg-slate-50 text-slate-700">
                    {{ __('Select all *') }}
                </button>
                <button type="button" id="clear-btn"
                        class="h-9 px-3 text-xs font-medium border border-slate-200 rounded-md hover:bg-slate-50 text-slate-500">
                    {{ __('Clear') }}
                </button>
            </div>
        </div>

        @if ($employees->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-slate-400">
                {{ __('No employees with an active contract.') }}
            </p>
        @else
        <table class="w-full text-sm" id="emp-table">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3 w-10">
                        <input type="checkbox" id="select-all-head"
                               class="w-4 h-4 rounded text-brand-600 focus:ring-brand-500">
                    </th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('Job title') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('Gross') }}</th>
                    <th class="px-4 py-3 text-center font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($employees as $emp)
                    @php
                        $csColor = match (mb_strtolower((string) $emp->contract_status)) {
                            'active'        => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                            'expiring soon' => 'bg-amber-50 text-amber-700 ring-amber-200',
                            'expired'       => 'bg-rose-50 text-rose-700 ring-rose-200',
                            default         => 'bg-slate-100 text-slate-600 ring-slate-200',
                        };
                        $searchKey = mb_strtolower(($emp->name ?? '') . ' ' . ($emp->emp_code ?? '') . ' ' . ($emp->job_title ?? ''));
                    @endphp
                    <tr class="emp-row hover:bg-slate-50/60 transition cursor-pointer"
                        data-search="{{ $searchKey }}">
                        <td class="px-4 py-3">
                            <input type="checkbox" name="employee_ids[]" value="{{ $emp->odoo_id }}"
                                   class="emp-cb w-4 h-4 rounded text-brand-600 focus:ring-brand-500">
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500 font-mono">{{ $emp->emp_code ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="grid place-items-center w-8 h-8 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-white text-xs font-bold flex-shrink-0">
                                    {{ mb_strtoupper(mb_substr($emp->name, 0, 1)) }}
                                </span>
                                <span class="font-medium text-slate-900">{{ $emp->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $emp->job_title ?: '—' }}</td>
                        <td class="px-4 py-3 text-end text-slate-700 tabular-nums">
                            @if ($emp->total_salary)
                                {{ number_format($emp->total_salary, 0) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($emp->contract_status)
                                <span class="inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $csColor }}">{{ __($emp->contract_status) }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Sticky action bar --}}
    <div class="sticky bottom-0 bg-white border border-slate-200 rounded-xl p-3 flex items-center justify-between shadow-soft">
        <p class="text-sm text-slate-600">
            <span id="bar-count" class="font-semibold text-brand-700">0</span>
            {{ __('employees selected') }}
            <span class="text-slate-400 mx-2">·</span>
            <span class="text-slate-500">{{ __('Total gross:') }}</span>
            <span id="bar-total" class="font-semibold text-emerald-700 tabular-nums">0</span>
            <span class="text-xs text-slate-400">{{ __('SAR') }}</span>
        </p>
        <div class="flex gap-2">
            <a href="{{ route('payslips.index') }}"
               class="h-9 px-4 inline-flex items-center text-sm text-slate-600 hover:bg-slate-100 rounded-md">{{ __('Cancel') }}</a>
            <button type="submit" id="submit-btn" disabled
                    class="h-9 px-5 inline-flex items-center gap-2 bg-emerald-600 text-white text-sm font-semibold rounded-md hover:bg-emerald-700 transition disabled:opacity-40 disabled:cursor-not-allowed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                {{ __('Generate payslips') }}
            </button>
        </div>
    </div>
</form>

<script>
(function () {
    const form        = document.getElementById('bulk-form');
    if (!form) return;
    const selectAllH  = document.getElementById('select-all-head');
    const selectAllB  = document.getElementById('select-all-btn');
    const clearBtn    = document.getElementById('clear-btn');
    const filter      = document.getElementById('emp-filter');
    const rows        = Array.from(document.querySelectorAll('.emp-row'));
    const countBadge  = document.getElementById('selected-count');
    const barCount    = document.getElementById('bar-count');
    const barTotal    = document.getElementById('bar-total');
    const submitBtn   = document.getElementById('submit-btn');
    const grossByOdoo = {};
    @foreach ($employees as $emp)
        grossByOdoo[{{ $emp->odoo_id }}] = {{ (float) ($emp->total_salary ?? 0) }};
    @endforeach

    function visibleCheckboxes() {
        return rows.filter(r => r.style.display !== 'none')
                   .map(r => r.querySelector('.emp-cb'));
    }

    function refresh() {
        const cbs = Array.from(document.querySelectorAll('.emp-cb'));
        const selected = cbs.filter(c => c.checked);
        const total = selected.reduce((s, c) => s + (grossByOdoo[c.value] || 0), 0);
        countBadge.textContent = selected.length;
        barCount.textContent   = selected.length;
        barTotal.textContent   = total.toLocaleString(undefined, {maximumFractionDigits: 0});
        submitBtn.disabled = selected.length === 0;
        const vis = visibleCheckboxes();
        const allVisChecked = vis.length > 0 && vis.every(c => c.checked);
        if (selectAllH) {
            selectAllH.checked = allVisChecked;
            selectAllH.indeterminate = !allVisChecked && vis.some(c => c.checked);
        }
    }

    document.querySelectorAll('.emp-cb').forEach(cb => cb.addEventListener('change', refresh));

    rows.forEach(r => r.addEventListener('click', e => {
        if (e.target.tagName === 'INPUT') return;
        const cb = r.querySelector('.emp-cb');
        cb.checked = !cb.checked;
        refresh();
    }));

    if (selectAllH) {
        selectAllH.addEventListener('change', () => {
            visibleCheckboxes().forEach(cb => { cb.checked = selectAllH.checked; });
            refresh();
        });
    }

    selectAllB.addEventListener('click', () => {
        document.querySelectorAll('.emp-cb').forEach(cb => { cb.checked = true; });
        refresh();
    });

    clearBtn.addEventListener('click', () => {
        document.querySelectorAll('.emp-cb').forEach(cb => { cb.checked = false; });
        refresh();
    });

    filter.addEventListener('input', () => {
        const q = filter.value.trim().toLowerCase();
        rows.forEach(r => {
            r.style.display = (!q || r.dataset.search.includes(q)) ? '' : 'none';
        });
        refresh();
    });

    form.addEventListener('submit', e => {
        const n = Array.from(document.querySelectorAll('.emp-cb')).filter(c => c.checked).length;
        if (n === 0) { e.preventDefault(); return; }
        const msg = "{{ __('Generate :n payslips? Compute will run per employee.') }}".replace(':n', n);
        if (!confirm(msg)) e.preventDefault();
        else submitBtn.disabled = true;
    });

    refresh();
})();
</script>
@endsection
