@extends('layouts.app')
@section('title', __('Finance'))

@section('content')
    <div class="max-w-xl mx-auto text-center py-16">
        <span class="inline-grid place-items-center w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-700 text-white shadow-soft mb-5">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8">
                <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>
            </svg>
        </span>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Finance') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
            {{ __('This module is under construction.') }}
        </p>
        <a href="https://{{ config('domains.portal') }}"
           class="inline-flex items-center gap-2 mt-6 h-10 px-4 rounded-md text-sm font-medium bg-brand-600 text-white hover:bg-brand-700 transition">
            {{ __('Back to portal') }}
        </a>
    </div>
@endsection
