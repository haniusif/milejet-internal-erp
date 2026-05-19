@extends('layouts.app')
@section('title', $employee->name)

@section('content')
@php $dir = app()->getLocale() === 'ar' ? 'rtl' : 'ltr'; @endphp
<a href="{{ route('employees.index') }}"
   class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
        @if ($dir === 'rtl')
            <path d="m9 18 6-6-6-6"/>
        @else
            <path d="m15 18-6-6 6-6"/>
        @endif
    </svg>
    {{ __('Back to employees list') }}
</a>

{{-- Hero profile card --}}
<div class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-5">
    <div class="h-24 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-900"></div>
    <div class="px-6 pb-5">
        <div class="flex flex-wrap items-end justify-between gap-4 -mt-12">
            <div class="flex items-end gap-4">
                @if ($extra['image'])
                    <img src="data:image/png;base64,{{ $extra['image'] }}"
                         alt="{{ $employee->name }}"
                         class="w-24 h-24 rounded-2xl object-cover ring-4 ring-white shadow-soft bg-white">
                @else
                    <span class="grid place-items-center w-24 h-24 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white text-3xl font-bold ring-4 ring-white shadow-soft">
                        {{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}
                    </span>
                @endif
                <div class="pb-2">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $employee->name }}</h1>
                    <p class="text-sm text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span>{{ $employee->job_title ?: __('No job title') }}</span>
                        @if ($employee->emp_code)
                            <span class="text-slate-300">·</span>
                            <span class="font-mono text-xs px-2 py-0.5 rounded bg-brand-50 text-brand-700 ring-1 ring-brand-200">{{ $employee->emp_code }}</span>
                        @endif
                        @if ($employee->iqama_id ?: $extra['identification_id'])
                            <span class="text-slate-300">·</span>
                            <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $employee->iqama_id ?: $extra['identification_id'] }}</span>
                        @endif
                        @if ($employee->contract_status)
                            @php
                                $csColor = match (mb_strtolower($employee->contract_status)) {
                                    'active'        => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'expiring soon' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                    'expired'       => 'bg-rose-50 text-rose-700 ring-rose-200',
                                    default         => 'bg-slate-100 text-slate-700 ring-slate-200',
                                };
                            @endphp
                            <span class="text-slate-300">·</span>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full ring-1 {{ $csColor }}">{{ __($employee->contract_status) }}</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 pb-2">
                @if ($employee->active)
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        {{ __('Active') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 ring-1 ring-slate-200">
                        {{ __('Inactive') }}
                    </span>
                @endif
                @can('employees.write')
                <a href="{{ route('employees.edit', $employee->id) }}"
                   class="inline-flex items-center gap-1.5 h-9 px-4 rounded-md bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    {{ __('Edit') }}
                </a>
                @endcan
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    {{-- Left column: contact + personal --}}
    <div class="space-y-5">
        {{-- Contact info --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                {{ __('Contact information') }}
            </h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Email') }}</dt>
                    <dd class="text-slate-900">
                        @if ($employee->work_email)
                            <a href="mailto:{{ $employee->work_email }}" class="text-brand-600 hover:underline">{{ $employee->work_email }}</a>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Work phone') }}</dt>
                    <dd class="text-slate-900 tabular-nums">{{ $employee->work_phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Mobile') }}</dt>
                    <dd class="text-slate-900 tabular-nums">{{ $employee->mobile_phone ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Personal info --}}
        @php
            $birthday = $employee->birthday ?: ($extra['birthday'] ? \Carbon\Carbon::parse($extra['birthday']) : null);
            $nationality = $employee->nationality ?: $extra['country_name'];
        @endphp
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                {{ __('Personal information') }}
            </h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Nationality') }}</dt>
                    <dd class="text-slate-900">{{ $nationality ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Date of birth') }}</dt>
                    <dd class="text-slate-900 tabular-nums">
                        @if ($birthday)
                            {{ \Carbon\Carbon::parse($birthday)->format('Y-m-d') }}
                            <span class="text-xs text-slate-500 ms-2">
                                ({{ __(':years years', ['years' => \Carbon\Carbon::parse($birthday)->age]) }})
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd>
                </div>
                @if ($employee->iqama_id)
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Iqama / National ID') }}</dt>
                    <dd class="text-slate-900 font-mono tabular-nums">{{ $employee->iqama_id }}</dd>
                </div>
                @endif
                @if ($employee->passport_id)
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Passport') }}</dt>
                    <dd class="text-slate-900 font-mono">{{ $employee->passport_id }}</dd>
                </div>
                @endif
                @if ($employee->region)
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Region') }}</dt>
                    <dd class="text-slate-900">{{ $employee->region }}</dd>
                </div>
                @endif
                @if ($employee->family_status || $employee->cchi_card_type)
                <div class="flex gap-4">
                    @if ($employee->family_status)
                    <div class="flex-1">
                        <dt class="text-xs text-slate-400 mb-0.5">{{ __('Family status') }}</dt>
                        <dd class="text-slate-900">{{ $employee->family_status }}</dd>
                    </div>
                    @endif
                    @if ($employee->cchi_card_type)
                    <div class="flex-1">
                        <dt class="text-xs text-slate-400 mb-0.5">{{ __('CCHI card') }}</dt>
                        <dd class="text-slate-900">{{ $employee->cchi_card_type }}</dd>
                    </div>
                    @endif
                </div>
                @endif
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Gender') }}</dt>
                    <dd class="text-slate-900">
                        {{ match ($extra['gender']) {
                            'male'   => __('Male'),
                            'female' => __('Female'),
                            'other'  => __('Other'),
                            default  => '—',
                        } }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Organization --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                    <path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/>
                </svg>
                {{ __('Organization') }}
            </h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Department') }}</dt>
                    <dd class="text-slate-900">{{ $employee->department_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">{{ __('Direct manager') }}</dt>
                    <dd class="text-slate-900">{{ $employee->parent_name ?: '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Right column (2/3): contract + activity --}}
    <div class="lg:col-span-2 space-y-5">
        @php
            $hasMasterData = $employee->master_imported_at !== null;
            $allowances = [
                ['label' => __('Housing'),       'value' => (float) $employee->allowance_house],
                ['label' => __('Rent'),          'value' => (float) $employee->allowance_rent],
                ['label' => __('Transport'),     'value' => (float) $employee->allowance_transport],
                ['label' => __('Car'),           'value' => (float) $employee->allowance_car],
                ['label' => __('Special'),       'value' => (float) $employee->allowance_special],
                ['label' => __('Project'),       'value' => (float) $employee->allowance_project],
                ['label' => __('Food'),          'value' => (float) $employee->allowance_food],
                ['label' => __('Other'),         'value' => (float) $employee->allowance_other],
                ['label' => __('Overtime'),      'value' => (float) $employee->ot_allowance],
            ];
            $allowances = array_filter($allowances, fn($a) => $a['value'] > 0);
            $monthlyCosts = [
                ['label' => __('GOSI'),               'value' => (float) $employee->gosi_pm],
                ['label' => __('Indemnity'),          'value' => (float) $employee->indemnity_pm],
                ['label' => __('Leave accrual'),      'value' => (float) $employee->leave_accrual_pm],
                ['label' => __('Medical insurance'),  'value' => (float) $employee->med_insurance_pm],
                ['label' => __('PA & life'),          'value' => (float) $employee->pa_insurance_pm],
                ['label' => __('Bonus (pm avg)'),     'value' => (float) $employee->bonus_pm],
                ['label' => __('ALT ticket'),         'value' => (float) $employee->alt_ticket],
            ];
            $monthlyCosts = array_filter($monthlyCosts, fn($c) => $c['value'] > 0);
            $totalMonthlyCost = array_sum(array_column($monthlyCosts, 'value')) + ((float) $employee->total_salary);
        @endphp

        {{-- Employment details (from Master Sheet) --}}
        @if ($hasMasterData)
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    {{ __('Employment') }}
                </h2>
                <span class="text-[10px] text-slate-400">{{ __('Imported :date', ['date' => $employee->master_imported_at?->diffForHumans()]) }}</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-slate-400 mb-1">{{ __('Date of joining') }}</p>
                    <p class="text-sm font-medium text-slate-900 tabular-nums">{{ $employee->date_of_joining?->format('Y-m-d') ?: '—' }}</p>
                    @if ($employee->date_of_joining)
                        <p class="text-[10px] text-slate-400">{{ $employee->date_of_joining->diffForHumans() }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">{{ __('Contract end') }}</p>
                    <p class="text-sm font-medium text-slate-900 tabular-nums">{{ $employee->contract_end_date?->format('Y-m-d') ?: '—' }}</p>
                    @if ($employee->contract_end_date)
                        <p class="text-[10px] {{ $employee->contract_end_date->isPast() ? 'text-rose-500' : 'text-slate-400' }}">{{ $employee->contract_end_date->diffForHumans() }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">{{ __('Contract type') }}</p>
                    <p class="text-sm font-medium text-slate-900">{{ $employee->contract_type ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">{{ __('Employment status') }}</p>
                    <p class="text-sm font-medium text-slate-900">{{ $employee->status_label ?: '—' }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- Contract --}}
        @if ($contract)
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                    </svg>
                    {{ __('Current contract') }}
                </h2>
                <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full {{ $contract->stateColor() }}">
                    {{ $contract->stateLabel() }}
                </span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-slate-400 mb-1">{{ __('Gross salary') }}</p>
                    <p class="text-2xl font-bold tracking-tight text-brand-700 tabular-nums">
                        {{ number_format($contract->wage, 0) }}
                        <span class="text-xs text-slate-400 font-normal">{{ __('SAR') }}</span>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">{{ __('Start date') }}</p>
                    <p class="text-sm font-medium text-slate-900 tabular-nums">
                        {{ $contract->date_start?->format('Y-m-d') ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">{{ __('End date') }}</p>
                    <p class="text-sm font-medium text-slate-900 tabular-nums">
                        {{ $contract->date_end?->format('Y-m-d') ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">{{ __('Reference') }}</p>
                    <p class="text-sm font-medium text-slate-700 truncate">{{ $contract->name }}</p>
                </div>
            </div>
        </div>
        @else
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 flex items-center gap-2">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>
            </svg>
            {{ __('No contract found for this employee.') }}
        </div>
        @endif

        {{-- Compensation breakdown (from Master Sheet) --}}
        @if ($hasMasterData && (float) $employee->total_salary > 0)
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-emerald-600">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    {{ __('Compensation breakdown') }}
                </h2>
                <span class="text-[10px] text-slate-400">{{ __('Per month, SAR') }}</span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-gradient-to-br from-brand-50 to-brand-100/50 rounded-lg p-3 ring-1 ring-brand-100">
                    <p class="text-[11px] uppercase tracking-wider text-brand-600 mb-1">{{ __('Gross / Total') }}</p>
                    <p class="text-xl font-bold tracking-tight text-brand-700 tabular-nums">{{ number_format($employee->total_salary, 2) }}</p>
                </div>
                <div class="bg-slate-50 rounded-lg p-3 ring-1 ring-slate-100">
                    <p class="text-[11px] uppercase tracking-wider text-slate-500 mb-1">{{ __('Basic salary') }}</p>
                    <p class="text-xl font-bold tracking-tight text-slate-800 tabular-nums">{{ number_format($employee->basic_salary, 2) }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">{{ __('wage × 10/13.5') }}</p>
                </div>
                <div class="bg-amber-50 rounded-lg p-3 ring-1 ring-amber-100">
                    <p class="text-[11px] uppercase tracking-wider text-amber-600 mb-1">{{ __('Employer cost (est.)') }}</p>
                    <p class="text-xl font-bold tracking-tight text-amber-700 tabular-nums">{{ number_format($totalMonthlyCost, 2) }}</p>
                </div>
            </div>

            @if (count($allowances) > 0)
            <div class="mb-4">
                <h3 class="text-[11px] uppercase tracking-wider text-slate-500 mb-2">{{ __('Allowances') }}</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    @foreach ($allowances as $a)
                        <div class="bg-slate-50 rounded-md px-3 py-2 flex items-baseline justify-between">
                            <span class="text-xs text-slate-500">{{ $a['label'] }}</span>
                            <span class="text-sm font-medium text-slate-900 tabular-nums">{{ number_format($a['value'], 0) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if (count($monthlyCosts) > 0)
            <div>
                <h3 class="text-[11px] uppercase tracking-wider text-slate-500 mb-2">{{ __('Monthly cost components') }}</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    @foreach ($monthlyCosts as $c)
                        <div class="bg-amber-50/50 rounded-md px-3 py-2 flex items-baseline justify-between ring-1 ring-amber-100/50">
                            <span class="text-xs text-amber-700">{{ $c['label'] }}</span>
                            <span class="text-sm font-medium text-amber-900 tabular-nums">{{ number_format($c['value'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if ((float) $employee->loan_balance > 0 || (float) $employee->bonus_eligibility_months > 0)
            <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap gap-4 text-xs">
                @if ((float) $employee->loan_balance > 0)
                    <div>
                        <span class="text-slate-400">{{ __('Outstanding loan:') }}</span>
                        <span class="text-rose-600 font-medium tabular-nums ms-1">{{ number_format($employee->loan_balance, 2) }} {{ __('SAR') }}</span>
                    </div>
                @endif
                @if ((float) $employee->bonus_eligibility_months > 0)
                    <div>
                        <span class="text-slate-400">{{ __('Bonus eligibility:') }}</span>
                        <span class="text-slate-700 font-medium tabular-nums ms-1">{{ number_format($employee->bonus_eligibility_months, 2) }} {{ __('months') }}</span>
                    </div>
                @endif
            </div>
            @endif
        </div>
        @endif

        {{-- Recent payslips --}}
        @if (auth()->user()->can('payslips.view'))
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-emerald-600">
                    <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/>
                </svg>
                {{ __('Recent payslips') }}
            </h2>
            @forelse ($recentPayslips as $p)
                <a href="{{ route('payslips.show', $p->id) }}"
                   class="flex items-center justify-between py-2.5 border-b last:border-0 border-slate-100 hover:bg-slate-50 -mx-2 px-2 rounded transition">
                    <div class="flex items-center gap-3">
                        <span class="grid place-items-center w-8 h-8 rounded-md bg-emerald-50 text-emerald-700">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/>
                            </svg>
                        </span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $p->number ?? __('Payslip #:id', ['id' => $p->id]) }}</p>
                            <p class="text-xs text-slate-500 tabular-nums">
                                {{ $p->date_from?->format('Y-m-d') }} → {{ $p->date_to?->format('Y-m-d') }}
                            </p>
                        </div>
                    </div>
                    <div class="text-end">
                        <p class="text-sm font-bold text-emerald-700 tabular-nums">{{ number_format($p->net_total, 2) }}</p>
                        <span class="inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full {{ $p->stateColor() }}">
                            {{ $p->stateLabel() }}
                        </span>
                    </div>
                </a>
            @empty
                <p class="text-sm text-slate-400 text-center py-4">{{ __('No payslips.') }}</p>
            @endforelse
        </div>
        @endif

        {{-- Recent leaves & attendance side by side --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-amber-600">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    {{ __('Recent leaves') }}
                </h2>
                @forelse ($recentLeaves as $leave)
                    <div class="flex items-center justify-between py-2 border-b last:border-0 border-slate-100">
                        <div>
                            <p class="text-sm text-slate-900 tabular-nums">
                                {{ $leave->date_from?->format('Y-m-d') }}
                            </p>
                            <p class="text-xs text-slate-500">{{ trans_choice(':count day', $leave->number_of_days, ['count' => $leave->number_of_days]) }} · {{ $leave->leave_type_name ?? '—' }}</p>
                        </div>
                        <span class="inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full {{ $leave->stateColor() }}">
                            {{ $leave->stateLabel() }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-4">{{ __('No leaves.') }}</p>
                @endforelse
            </div>

            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                    </svg>
                    {{ __('Recent attendance records') }}
                </h2>
                @forelse ($recentAttendances as $att)
                    <div class="flex items-center justify-between py-2 border-b last:border-0 border-slate-100 text-sm">
                        <div>
                            <p class="text-slate-900 tabular-nums">{{ $att->check_in?->format('Y-m-d H:i') }}</p>
                            <p class="text-xs text-slate-500 tabular-nums">
                                @if ($att->check_out)
                                    {{ __('Out :time · :hours hours', ['time' => $att->check_out->format('H:i'), 'hours' => number_format($att->worked_hours, 2)]) }}
                                @else
                                    <span class="text-emerald-600 font-medium">{{ __('Currently in') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-4">{{ __('No attendance records.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
