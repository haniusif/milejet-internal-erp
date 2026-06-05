@extends('layouts.app')
@section('title', __('New vehicle'))

@section('content')
<div class="max-w-xl mx-auto">
    <a href="{{ route('fleet.index') }}"
       class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-4">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            @if (app()->getLocale() === 'ar')
                <path d="m9 18 6-6-6-6"/>
            @else
                <path d="m15 18-6-6 6-6"/>
            @endif
        </svg>
        {{ __('Back to vehicles') }}
    </a>

    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6">
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mb-5">{{ __('New vehicle') }}</h1>

        <form method="POST" action="{{ route('fleet.vehicles.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Model') }} <span class="text-rose-500">*</span></label>
                <select name="model_id" required
                        class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    <option value="">{{ __('— Select —') }}</option>
                    @foreach ($models as $m)
                        <option value="{{ $m->odoo_id }}" @selected(old('model_id') == $m->odoo_id)>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('License plate') }}</label>
                    <input type="text" name="license_plate" value="{{ old('license_plate') }}" dir="ltr"
                           class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                  focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Model year') }}</label>
                    <input type="text" name="model_year" value="{{ old('model_year') }}" placeholder="2024" dir="ltr"
                           class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                  focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('VIN') }}</label>
                    <input type="text" name="vin_sn" value="{{ old('vin_sn') }}" dir="ltr"
                           class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                                  focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Fuel') }}</label>
                    <select name="fuel_type"
                            class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach (['gasoline' => __('Gasoline'), 'diesel' => __('Diesel'), 'electric' => __('Electric'), 'full_hybrid' => __('Hybrid'), 'lpg' => __('LPG')] as $val => $label)
                            <option value="{{ $val }}" @selected(old('fuel_type') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1.5 text-slate-700 dark:text-slate-200">{{ __('Driver') }}</label>
                <select name="employee_id"
                        class="w-full h-10 rounded-lg px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->name }}</option>
                    @endforeach
                </select>
            </div>

            <button class="w-full h-10 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">
                {{ __('Create vehicle') }}
            </button>
        </form>
    </div>
</div>
@endsection
