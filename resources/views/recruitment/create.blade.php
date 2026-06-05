@extends('layouts.app')
@section('title', __('New applicant'))

@section('content')
<div class="max-w-xl mx-auto">
    <a href="{{ route('recruitment.applicants') }}"
       class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-4">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            @if (app()->getLocale() === 'ar')
                <path d="m9 18 6-6-6-6"/>
            @else
                <path d="m15 18-6-6 6-6"/>
            @endif
        </svg>
        {{ __('Back to applicants') }}
    </a>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6">
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mb-5">{{ __('New applicant') }}</h1>

        <form method="POST" action="{{ route('recruitment.applicants.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Candidate name') }} <span class="text-rose-500">*</span></label>
                <input type="text" name="partner_name" value="{{ old('partner_name') }}" required
                       class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                              focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Job position') }} <span class="text-rose-500">*</span></label>
                <select name="job_id" required
                        class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach ($jobs as $j)
                        <option value="{{ $j->odoo_id }}" @selected(old('job_id') == $j->odoo_id)>
                            {{ $j->name }}{{ $j->department_name ? ' — '.$j->department_name : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Email') }}</label>
                    <input type="email" name="email_from" value="{{ old('email_from') }}" dir="ltr"
                           class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                  focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Phone') }}</label>
                    <input type="text" name="partner_phone" value="{{ old('partner_phone') }}" dir="ltr"
                           class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                  focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Expected salary (SAR)') }}</label>
                <input type="number" name="salary_expected" value="{{ old('salary_expected') }}" min="0" step="100" dir="ltr"
                       class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                              focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Notes') }}</label>
                <textarea name="description" rows="4"
                          class="w-full rounded-lg px-3 py-2 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">{{ old('description') }}</textarea>
            </div>

            <button class="w-full h-10 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">
                {{ __('Add applicant') }}
            </button>
        </form>
    </div>
</div>
@endsection
