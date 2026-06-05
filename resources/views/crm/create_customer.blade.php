@extends('layouts.app')
@section('title', __('New customer'))

@section('content')
<div class="max-w-xl mx-auto">
    <a href="{{ route('crm.customers') }}"
       class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-4">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            @if (app()->getLocale() === 'ar')
                <path d="m9 18 6-6-6-6"/>
            @else
                <path d="m15 18-6-6 6-6"/>
            @endif
        </svg>
        {{ __('Back to customers') }}
    </a>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6">
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mb-5">{{ __('New customer') }}</h1>

        <form method="POST" action="{{ route('crm.customers.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Name') }} <span class="text-rose-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                              focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="is_company" value="1" @checked(old('is_company'))
                       class="rounded border-slate-300 dark:border-slate-700 text-brand-600 focus:ring-brand-500">
                {{ __('This is a company') }}
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" dir="ltr"
                           class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                  focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" dir="ltr"
                           class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                  focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('City') }}</label>
                    <input type="text" name="city" value="{{ old('city') }}"
                           class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                  focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('VAT number') }}</label>
                    <input type="text" name="vat" value="{{ old('vat') }}" dir="ltr"
                           class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                  focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
                </div>
            </div>

            <button class="w-full h-10 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">
                {{ __('Create customer') }}
            </button>
        </form>
    </div>
</div>
@endsection
