@extends('layouts.app')
@section('title', __('Fleet'))

@section('content')
    <div class="max-w-xl mx-auto text-center py-16">
        <span class="inline-grid place-items-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-soft mb-5">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8">
                <path d="M5 17h-2v-6l2-5h9l4 5h3a2 2 0 0 1 2 2v4h-2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/>
            </svg>
        </span>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Fleet') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
            {{ __('This module is under construction.') }}
        </p>
        <a href="https://{{ config('domains.portal') }}"
           class="inline-flex items-center gap-2 mt-6 h-10 px-4 rounded-md text-sm font-medium bg-brand-600 text-white hover:bg-brand-700 transition">
            {{ __('Back to portal') }}
        </a>
    </div>
@endsection
