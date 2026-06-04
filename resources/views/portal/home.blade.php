@extends('layouts.app')
@section('title', __('Portal'))
@section('chromeless', '1')

@section('content')
    @php
        $locale = app()->getLocale();
        $links  = config('domains.links');
        $otherLocale = $locale === 'ar' ? 'en' : 'ar';
        $theme = session('theme', 'light');
        $otherTheme = $theme === 'dark' ? 'light' : 'dark';

        $apps = [
            [
                'label' => __('HR System'),
                'desc'  => __('Employees, leaves, attendance, contracts and payroll'),
                'href'  => $links['hr'] ?: route('dashboard'),
                'external' => (bool) $links['hr'],
                'grad'  => 'from-brand-500 to-brand-700',
                'icon'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            ],
            [
                'label' => __('CRM'),
                'desc'  => __('Customers, leads and sales pipeline'),
                'href'  => $links['crm'],
                'external' => true,
                'grad'  => 'from-sky-500 to-sky-700',
                'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>',
            ],
            [
                'label' => __('Fleet'),
                'desc'  => __('Vehicles, drivers and trips'),
                'href'  => $links['fleet'],
                'external' => true,
                'grad'  => 'from-emerald-500 to-emerald-700',
                'icon'  => '<path d="M5 17h-2v-6l2-5h9l4 5h3a2 2 0 0 1 2 2v4h-2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/>',
            ],
            [
                'label' => __('Finance'),
                'desc'  => __('Accounting, invoices and financial reports'),
                'href'  => $links['finance'],
                'external' => true,
                'grad'  => 'from-amber-500 to-amber-700',
                'icon'  => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
            ],
            [
                'label' => __('ERP (Odoo)'),
                'desc'  => __('Source of truth — accounting, inventory and HR backend'),
                'href'  => $links['erp'],
                'external' => true,
                'grad'  => 'from-indigo-500 to-indigo-700',
                'icon'  => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            ],
        ];
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

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($apps as $app)
                <a href="{{ $app['href'] }}" @if($app['external']) rel="noopener" @endif
                   class="group flex items-start gap-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 transition
                          hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-soft">
                    <span class="grid place-items-center w-12 h-12 rounded-xl bg-gradient-to-br {{ $app['grad'] }} text-white shadow-sm shrink-0">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                            {!! $app['icon'] !!}
                        </svg>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="flex items-center gap-1.5 text-base font-semibold text-slate-900 dark:text-slate-100">
                            {{ $app['label'] }}
                            @if ($app['external'])
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round"
                                     class="w-3.5 h-3.5 text-slate-300 dark:text-slate-600 group-hover:text-slate-500 dark:group-hover:text-slate-400 transition">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/>
                                </svg>
                            @endif
                        </span>
                        <span class="block text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $app['desc'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@endsection
