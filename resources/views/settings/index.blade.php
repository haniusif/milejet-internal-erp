@extends('layouts.app')
@section('title', __('Settings'))

@section('content')
@php
    // [route, title, description, count, icon path]. Countries comes first.
    $cards = [
        ['settings.countries', __('Countries'), __('Nationalities & dialing codes synced from Odoo'), $counts['countries'],
         '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'],
        ['departments.index', __('Departments'), __('Company departments & managers'), $counts['departments'],
         '<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/>'],
        ['work-locations.index', __('Offices'), __('Branches, offices & attendance geofences'), $counts['offices'],
         '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>'],
    ];
    if (Auth::user()->can('config.view')) {
        $cards[] = ['settings.config', __('Configuration'), __('Odoo connection, domains & system defaults'), null,
            '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>'];
    }
@endphp

<div class="mb-5">
    <p class="text-xs uppercase tracking-wider text-slate-400">{{ __('HR module') }}</p>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">{{ __('Settings') }}</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Reference data used across the HR module.') }}</p>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($cards as [$rname, $title, $desc, $count, $icon])
        <a href="{{ route($rname) }}"
           class="group bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-card
                  hover:border-brand-300 dark:hover:border-brand-700 hover:shadow-soft transition">
            <div class="flex items-start justify-between">
                <span class="grid place-items-center w-11 h-11 rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300
                             group-hover:bg-brand-600 group-hover:text-white transition">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="ico">
                        {!! $icon !!}
                    </svg>
                </span>
                @if (!is_null($count))
                <span class="inline-flex items-center justify-center min-w-[1.75rem] h-6 px-2 text-xs font-semibold rounded-full
                             bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300 tabular-nums">
                    {{ $count }}
                </span>
                @endif
            </div>
            <h2 class="mt-4 text-base font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $desc }}</p>
        </a>
    @endforeach
</div>
@endsection
