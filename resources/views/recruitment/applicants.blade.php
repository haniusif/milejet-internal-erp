@extends('layouts.app')
@section('title', __('Applicants'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Recruitment') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">
            {{ __('Applicants') }} <span class="text-slate-400 font-medium">({{ $applicants->total() }})</span>
        </h1>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('recruitment.jobs') }}"
           class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200
                  dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition">
            {{ __('Job positions') }}
        </a>
        @can('recruitment.write')
        <a href="{{ route('recruitment.applicants.create') }}"
           class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition shadow-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            {{ __('New applicant') }}
        </a>
        @endcan
    </div>
</div>

<form method="GET" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
    <div class="relative flex-1 min-w-[180px]">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             class="ico-sm absolute {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 text-slate-400">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by name, email, or position...') }}"
               class="w-full h-9 {{ app()->getLocale() === 'ar' ? 'pe-9 ps-3' : 'ps-9 pe-3' }} border border-slate-200 dark:border-slate-700 rounded-md text-sm
                      bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                      focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:focus:ring-brand-900 focus:outline-none">
    </div>
    <select name="job_id" onchange="this.form.submit()"
            class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 max-w-[14rem]">
        <option value="">{{ __('All positions') }}</option>
        @foreach ($jobs as $j)
            <option value="{{ $j->odoo_id }}" @selected(request('job_id') == $j->odoo_id)>{{ $j->name }}</option>
        @endforeach
    </select>
    <select name="stage_id" onchange="this.form.submit()"
            class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
        <option value="">{{ __('All stages') }}</option>
        @foreach ($stages as $s)
            <option value="{{ $s->odoo_id }}" @selected(request('stage_id') == $s->odoo_id)>{{ $s->name }}</option>
        @endforeach
    </select>
    <select name="status" onchange="this.form.submit()"
            class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
        <option value="" @selected(!request('status'))>{{ __('In pipeline') }}</option>
        <option value="refused" @selected(request('status') === 'refused')>{{ __('Refused') }}</option>
        <option value="all" @selected(request('status') === 'all')>{{ __('All') }}</option>
    </select>
    <button class="h-9 px-4 rounded-md bg-slate-900 dark:bg-slate-700 text-white text-sm">{{ __('Filter') }}</button>
</form>

<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 dark:bg-slate-800/60 text-start text-xs text-slate-500 dark:text-slate-400">
                    <th class="text-start font-medium px-4 py-3">{{ __('Candidate') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Position') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Applied') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Expected salary') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Stage') }}</th>
                    @can('recruitment.write')
                    <th class="text-end font-medium px-4 py-3">{{ __('Actions') }}</th>
                    @endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($applicants as $a)
                    <tr class="{{ $a->isRefused() ? 'opacity-60' : '' }} hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ $a->partner_name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $a->email_from ?: '' }}{{ $a->email_from && ($a->partner_phone || $a->partner_mobile) ? ' · ' : '' }}{{ $a->partner_phone ?: $a->partner_mobile }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                            <p>{{ $a->job_name ?: '—' }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ $a->department_name }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 tabular-nums text-xs">
                            {{ $a->odoo_create_date?->format('Y-m-d') ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300 tabular-nums">
                            {{ $a->salary_expected ? number_format($a->salary_expected, 0) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $a->statusColor() }}">
                                {{ $a->statusLabel() }}
                            </span>
                        </td>
                        @can('recruitment.write')
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                @if (!$a->isRefused())
                                    <form action="{{ route('recruitment.applicants.stage', $a->id) }}" method="POST">
                                        @csrf
                                        <select name="stage_id" onchange="this.form.submit()" title="{{ __('Move to stage') }}"
                                                class="h-8 px-2 border border-slate-200 dark:border-slate-700 rounded-md text-xs bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200">
                                            @foreach ($stages as $s)
                                                <option value="{{ $s->odoo_id }}" @selected($a->odoo_stage_id == $s->odoo_id)>{{ $s->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    <form action="{{ route('recruitment.applicants.refuse', $a->id) }}" method="POST"
                                          onsubmit="return confirm('{{ __('Refuse this applicant?') }}')">
                                        @csrf
                                        <button type="submit" title="{{ __('Refuse') }}"
                                                class="inline-flex items-center justify-center h-8 w-8 rounded-md text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/30 transition">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                                <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>
                                            </svg>
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('recruitment.applicants.restore', $a->id) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 h-8 px-2.5 rounded-md text-xs font-medium text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-900/30 transition">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
                                            </svg>
                                            {{ __('Restore') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-12 text-sm text-slate-400 dark:text-slate-500">{{ __('No applicants found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $applicants->links() }}</div>
@endsection
