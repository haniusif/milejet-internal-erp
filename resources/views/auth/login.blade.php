@extends('layouts.app')
@section('title', __('Login'))

@section('content')
@php
    $locale      = app()->getLocale();
    $otherLocale = $locale === 'ar' ? 'en' : 'ar';
    $theme       = session('theme', 'light');
    $otherTheme  = $theme === 'dark' ? 'light' : 'dark';

    // Brand the page after the module living on this host.
    // The title.* keys hold the form used next to "MileJet" — Arabic uses
    // the genitive construct ("بوابة MileJet"), so the order is swapped below.
    $host    = request()->getHost();
    $modules = [
        config('domains.portal')  => __('title.portal'),
        config('domains.hr')      => __('title.hr'),
        config('domains.crm')     => __('title.crm'),
        config('domains.fleet')   => __('title.fleet'),
        config('domains.finance') => __('title.finance'),
    ];
    $module = $modules[$host] ?? __('title.portal');
@endphp

<div class="min-h-[88vh] flex flex-col items-center justify-center">

    {{-- Guest controls (no header on this page) --}}
    <div class="w-full max-w-md flex items-center justify-end gap-2 mb-3">
        <a href="{{ route('preferences.locale', $otherLocale) }}"
           title="{{ $otherLocale === 'ar' ? __('Switch to Arabic') : __('Switch to English') }}"
           class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md text-xs font-semibold
                  bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100
                  dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-800 dark:hover:bg-slate-800 transition">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
            {{ strtoupper($otherLocale) }}
        </a>
        <a href="{{ route('preferences.theme', $otherTheme) }}" title="{{ __('Toggle theme') }}"
           class="inline-flex items-center justify-center h-9 w-9 rounded-md
                  bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100
                  dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-800 dark:hover:bg-slate-800 transition">
            @if ($theme === 'dark')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                    <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                </svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            @endif
        </a>
    </div>

    <div class="w-full max-w-md bg-white dark:bg-slate-900 p-8 rounded-xl shadow-soft ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="text-center mb-7">
            <span class="inline-block rounded-xl bg-white px-4 py-2 ring-1 ring-slate-200 dark:ring-0 mb-4">
                <img src="{{ asset('img/milejet-logo.png') }}" alt="MileJet" class="h-14 w-auto">
            </span>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                @if ($locale === 'ar')
                    <span class="text-brand-600 dark:text-brand-400">{{ $module }}</span> MileJet
                @else
                    MileJet <span class="text-brand-600 dark:text-brand-400">{{ $module }}</span>
                @endif
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1.5">{{ __('Sign in to your account') }}</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" placeholder="your.email@company.com" dir="ltr"
                       class="w-full h-11 rounded-lg px-3 border border-slate-300 dark:border-slate-700
                              bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                              placeholder:text-slate-400 dark:placeholder:text-slate-500
                              focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Password') }}</label>
                <input type="password" name="password" required autocomplete="current-password" dir="ltr"
                       class="w-full h-11 rounded-lg px-3 border border-slate-300 dark:border-slate-700
                              bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                              focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none transition">
            </div>

            <button class="w-full h-11 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-semibold
                           focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 focus:outline-none transition">
                {{ __('Sign in') }}
            </button>
        </form>
    </div>
</div>
@endsection
