@extends('layouts.app')
@section('title', __('CRM'))

@section('content')
    <div class="max-w-xl mx-auto text-center py-16">
        <span class="inline-grid place-items-center w-16 h-16 rounded-2xl bg-gradient-to-br from-sky-500 to-sky-700 text-white shadow-soft mb-5">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>
            </svg>
        </span>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('CRM') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
            {{ __('This module is under construction.') }}
        </p>
        <a href="https://{{ config('domains.portal') }}"
           class="inline-flex items-center gap-2 mt-6 h-10 px-4 rounded-md text-sm font-medium bg-brand-600 text-white hover:bg-brand-700 transition">
            {{ __('Back to portal') }}
        </a>
    </div>
@endsection
