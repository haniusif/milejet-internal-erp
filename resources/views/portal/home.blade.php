@extends('layouts.app')
@section('title', __('Portal'))
@section('chromeless', '1')

@section('content')
    @php
        $locale = app()->getLocale();
        $otherLocale = $locale === 'ar' ? 'en' : 'ar';
        $theme = session('theme', 'light');
        $otherTheme = $theme === 'dark' ? 'light' : 'dark';
    @endphp

    <div class="max-w-4xl mx-auto">
        {{-- Minimal controls (no header on this page) --}}
        <div class="flex items-center justify-end gap-2 mb-2">
            <a href="{{ route('preferences.locale', $otherLocale) }}"
               title="{{ $otherLocale === 'ar' ? __('Switch to Arabic') : __('Switch to English') }}"
               class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md text-xs font-semibold
                      bg-slate-100 text-slate-700 hover:bg-slate-200
                      dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                    <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
                {{ strtoupper($otherLocale) }}
            </a>
            <a href="{{ route('preferences.theme', $otherTheme) }}" title="{{ __('Toggle theme') }}"
               class="inline-flex items-center justify-center h-9 w-9 rounded-md
                      bg-slate-100 text-slate-700 hover:bg-slate-200
                      dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition">
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
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" title="{{ __('Logout') }}"
                        class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md text-xs font-semibold
                               bg-slate-100 text-rose-600 hover:bg-rose-50
                               dark:bg-slate-800 dark:text-rose-400 dark:hover:bg-rose-900/30 transition">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>
                    </svg>
                    {{ __('Logout') }}
                </button>
            </form>
        </div>

        <div class="text-center mb-8 mt-4">
            <p class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Portal') }}</p>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">
                {{ __('Welcome, :name', ['name' => explode(' ', Auth::user()->name)[0]]) }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Choose an application to continue') }}</p>
        </div>

        @include('partials.module-cards')
    </div>
@endsection
