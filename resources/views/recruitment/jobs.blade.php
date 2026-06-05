@extends('layouts.app')
@section('title', __('Recruitment'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Human Resources') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">
            {{ __('Job positions') }} <span class="text-slate-400 font-medium">({{ $jobs->total() }})</span>
        </h1>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('recruitment.applicants') }}"
           class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200
                  dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            {{ __('Applicants') }}
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
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search positions...') }}"
               class="w-full h-9 {{ app()->getLocale() === 'ar' ? 'pe-9 ps-3' : 'ps-9 pe-3' }} border border-slate-200 dark:border-slate-700 rounded-md text-sm
                      bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                      focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:focus:ring-brand-900 focus:outline-none">
    </div>
    <select name="department_id" onchange="this.form.submit()"
            class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
        <option value="">{{ __('All departments') }}</option>
        @foreach ($departments as $d)
            <option value="{{ $d->odoo_department_id }}" @selected(request('department_id') == $d->odoo_department_id)>{{ $d->department_name }}</option>
        @endforeach
    </select>
    <button class="h-9 px-4 rounded-md bg-slate-900 dark:bg-slate-700 text-white text-sm">{{ __('Filter') }}</button>
</form>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
    @forelse ($jobs as $job)
        <a href="{{ route('recruitment.applicants', ['job_id' => $job->odoo_id]) }}"
           class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4
                  hover:border-brand-300 dark:hover:border-brand-700 hover:shadow-soft transition">
            <div class="flex items-start justify-between gap-2 mb-2">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 leading-snug">{{ $job->name }}</h2>
                @if ($job->no_of_recruitment > 0)
                    <span class="shrink-0 inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800">
                        {{ __(':count open', ['count' => $job->no_of_recruitment]) }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">{{ $job->department_name ?: '—' }}</p>
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-400 dark:text-slate-500">
                    {{ trans_choice(':count applicant|:count applicants', $job->open_applicants_count, ['count' => $job->open_applicants_count]) }}
                </span>
                @if ($job->recruiter_name)
                    <span class="text-slate-400 dark:text-slate-500 truncate max-w-[10rem]">{{ $job->recruiter_name }}</span>
                @endif
            </div>
        </a>
    @empty
        <div class="col-span-full text-center py-12 text-sm text-slate-400 dark:text-slate-500">{{ __('No job positions found.') }}</div>
    @endforelse
</div>

<div class="mt-5">{{ $jobs->links() }}</div>
@endsection
