@php
    $locale = app()->getLocale();
    $dir    = $locale === 'ar' ? 'rtl' : 'ltr';
    $theme  = session('theme', 'light');

    // Which module is this page part of? Route name wins (module pages are
    // domain-agnostic); the host decides for neutral routes such as /login.
    $host   = request()->getHost();
    $module = match (true) {
        request()->routeIs('crm.*')     => 'crm',
        request()->routeIs('fleet.*')   => 'fleet',
        request()->routeIs('finance.*') => 'finance',
        request()->routeIs('dashboard', 'employees.*', 'departments.*', 'work-locations.*',
                           'leaves.*', 'attendances.*', 'recruitment.*', 'contracts.*', 'payslips.*') => 'hr',
        $host === config('domains.crm')     => 'crm',
        $host === config('domains.fleet')   => 'fleet',
        $host === config('domains.finance') => 'finance',
        default                             => 'hr',
    };
    $moduleTitle = __("title.{$module}"); // same keys the login page brands with
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}" class="{{ $theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $moduleTitle)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Cairo', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        // MileJet brand — navy + orange from the logo
                        brand: {
                            50:  '#f0f6fb', 100: '#dceaf5', 200: '#bdd6ea',
                            300: '#8fb8d8', 400: '#5a93c0', 500: '#3674a6',
                            600: '#265c8a', 700: '#1d4970', 800: '#14375a',
                            900: '#0d2943',
                        },
                        accent: {
                            50:  '#fef4ec', 100: '#fde8db', 200: '#fbd0b5',
                            300: '#f7a877', 400: '#f48144', 500: '#ef6925',
                            600: '#d9571a', 700: '#b34715', 800: '#8f3a14',
                            900: '#743112',
                        }
                    },
                    boxShadow: {
                        card: '0 1px 2px rgb(15 23 42 / 0.04), 0 1px 3px rgb(15 23 42 / 0.06)',
                        soft: '0 4px 12px rgb(15 23 42 / 0.06)',
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: @if($locale === 'ar') 'Cairo', sans-serif @else 'Inter', sans-serif @endif;
            -webkit-font-smoothing: antialiased;
        }
        .ico    { width: 1.125rem; height: 1.125rem; flex-shrink: 0; }
        .ico-sm { width: 1rem; height: 1rem; flex-shrink: 0; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 min-h-screen antialiased transition-colors">

@auth
@sectionMissing('chromeless')
@php
    $icons = [
        'dashboard'        => '<path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/>',
        'employees.index'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'departments.index' => '<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/>',
        'work-locations.index' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'leaves.index'     => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'attendances.index' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'recruitment.jobs' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6M8 11h6"/>',
        'contracts.index'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'payslips.index'   => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'crm.index'        => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'crm.customers'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'fleet.index'      => '<path d="M5 17h-2v-6l2-5h9l4 5h3a2 2 0 0 1 2 2v4h-2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/>',
        'fleet.services'   => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'settings.index'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'settings.countries' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'settings.config'  => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'finance.invoices' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/>',
        'finance.bills'    => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
    ];
    $user = Auth::user();
    // Per-module nav: [route, label, gate, active-route patterns].
    // Plain employees (no HR staff role) only get their own corner of HR:
    // profile, leaves, attendance and payslips — all self-scoped server-side.
    $navByModule = [
        'hr' => $user->can('hr.view_all') ? [
            ['dashboard',            __('Dashboard'),   null,               ['dashboard']],
            ['employees.index',      __('Employees'),   null,               ['employees.*']],
            ['leaves.index',         __('Leaves'),      null,               ['leaves.*']],
            ['attendances.index',    __('Attendance'),  null,               ['attendances.*']],
            ['recruitment.jobs',     __('Recruitment'), 'recruitment.view', ['recruitment.*']],
            ['contracts.index',      __('Contracts'),   'contracts.view',   ['contracts.*']],
            ['payslips.index',       __('Payslips'),    'payslips.view',    ['payslips.*']],
        ] : [
            ['employees.index',      __('My Profile'),  null,               ['employees.*']],
            ['leaves.index',         __('My Leaves'),   null,               ['leaves.*']],
            ['attendances.index',    __('Attendance'),  null,               ['attendances.*']],
            ['payslips.index',       __('My Payslips'), null,               ['payslips.*']],
        ],
        'crm' => [
            ['crm.index',     __('Pipeline'),  null, ['crm.index', 'crm.leads.*']],
            ['crm.customers', __('Customers'), null, ['crm.customers*']],
        ],
        'fleet' => [
            ['fleet.index',    __('Vehicles'), null, ['fleet.index', 'fleet.vehicles.*']],
            ['fleet.services', __('Services'), null, ['fleet.services']],
        ],
        'finance' => [
            ['finance.invoices', __('Invoices'), null, ['finance.invoices', 'finance.show']],
            ['finance.bills',    __('Bills'),    null, ['finance.bills']],
        ],
    ];
    $nav = $navByModule[$module];
    // HR reference data lives under a "Settings" dropdown (HR staff only):
    // [route, label, active-route patterns]. First entry is the settings hub.
    $hrSettings = $module === 'hr' && $user->can('hr.view_all') ? array_values(array_filter([
        ['settings.index',       __('Settings'),    ['settings.index']],
        ['settings.countries',   __('Countries'),   ['settings.countries']],
        ['departments.index',    __('Departments'), ['departments.*']],
        ['work-locations.index', __('Offices'),     ['work-locations.*']],
        $user->can('config.view') ? ['settings.config', __('Configuration'), ['settings.config']] : null,
    ])) : [];
    $moduleHome = [
        'hr'      => 'dashboard',
        'crm'     => 'crm.index',
        'fleet'   => 'fleet.index',
        'finance' => 'finance.invoices',
    ][$module];
    $rolesPretty = [
        'admin'           => [__('Role: System Admin'),     'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/40 dark:text-rose-300 dark:ring-rose-800'],
        'hr_manager'      => [__('Role: HR Manager'),       'bg-brand-50 text-brand-700 ring-brand-200 dark:bg-brand-900/40 dark:text-brand-300 dark:ring-brand-800'],
        'hr_officer'      => [__('Role: HR Officer'),       'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-300 dark:ring-indigo-800'],
        'payroll_manager' => [__('Role: Payroll Manager'),  'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-800'],
        'payroll_officer' => [__('Role: Payroll Officer'),  'bg-teal-50 text-teal-700 ring-teal-200 dark:bg-teal-900/40 dark:text-teal-300 dark:ring-teal-800'],
        'leave_manager'   => [__('Role: Leave Manager'),    'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800'],
        'recruitment_manager' => [__('Role: Recruitment Manager'), 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-900/40 dark:text-sky-300 dark:ring-sky-800'],
        'recruitment_officer' => [__('Role: Recruitment Officer'), 'bg-cyan-50 text-cyan-700 ring-cyan-200 dark:bg-cyan-900/40 dark:text-cyan-300 dark:ring-cyan-800'],
        'crm_manager'         => [__('Role: Sales Manager'),       'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-900/40 dark:text-violet-300 dark:ring-violet-800'],
        'crm_user'            => [__('Role: Salesperson'),         'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-200 dark:bg-fuchsia-900/40 dark:text-fuchsia-300 dark:ring-fuchsia-800'],
        'fleet_manager'       => [__('Role: Fleet Manager'),       'bg-lime-50 text-lime-700 ring-lime-200 dark:bg-lime-900/40 dark:text-lime-300 dark:ring-lime-800'],
        'fleet_officer'       => [__('Role: Fleet Officer'),       'bg-green-50 text-green-700 ring-green-200 dark:bg-green-900/40 dark:text-green-300 dark:ring-green-800'],
        'finance_manager'     => [__('Role: Finance Manager'),     'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800'],
        'finance_officer'     => [__('Role: Accountant'),          'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-900/40 dark:text-orange-300 dark:ring-orange-800'],
        'employee'        => [__('Role: Employee'),         'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
    ];
    $otherLocale = $locale === 'ar' ? 'en' : 'ar';
    $otherTheme  = $theme === 'dark' ? 'light' : 'dark';
@endphp

<header class="sticky top-0 z-20 bg-white/85 dark:bg-slate-900/85 backdrop-blur border-b border-slate-200 dark:border-slate-800">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-2">
                <a href="{{ route($moduleHome) }}" class="flex items-center {{ $dir === 'rtl' ? 'ml-4' : 'mr-4' }}">
                    {{-- white chip keeps the navy logo visible on the dark-mode header --}}
                    <span class="inline-flex items-center rounded-lg dark:bg-white px-1.5 py-1">
                        <img src="{{ asset('img/milejet-logo.png') }}" alt="MileJet" class="h-8 w-auto">
                    </span>
                </a>
                <nav class="hidden md:flex items-center gap-0.5">
                    @foreach ($nav as [$rname, $label, $gate, $patterns])
                        @continue($module === 'hr' && $rname === 'dashboard') {{-- the logo links to the dashboard --}}
                        @if (!$gate || $user->can($gate))
                            @php $active = request()->routeIs(...$patterns); @endphp
                            <a href="{{ route($rname) }}" title="{{ $label }}"
                               class="inline-flex items-center gap-2 px-2.5 h-9 rounded-md text-sm whitespace-nowrap transition
                                      {{ $active
                                        ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-900/40 dark:text-brand-300'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                    {!! $icons[$rname] ?? '' !!}
                                </svg>
                                <span class="hidden xl:inline">{{ $label }}</span>
                            </a>
                        @endif
                    @endforeach

                    @if ($hrSettings)
                        @php $settingsActive = request()->routeIs('settings.*', 'departments.*', 'work-locations.*'); @endphp
                        <div class="relative" id="hr-settings-wrap">
                            <button type="button" id="hr-settings-button"
                                    aria-haspopup="true" aria-expanded="false"
                                    class="inline-flex items-center gap-2 px-2.5 h-9 rounded-md text-sm whitespace-nowrap transition
                                           {{ $settingsActive
                                             ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-900/40 dark:text-brand-300'
                                             : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                    <circle cx="12" cy="12" r="3"/>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                </svg>
                                <span class="hidden xl:inline">{{ __('Settings') }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ico-sm text-slate-400">
                                    <path d="m6 9 6 6 6-6"/>
                                </svg>
                            </button>
                            <div id="hr-settings-menu"
                                 class="hidden absolute start-0 mt-1 w-48 rounded-lg bg-white dark:bg-slate-900 shadow-soft ring-1 ring-slate-200 dark:ring-slate-800 z-30 p-1">
                                @foreach ($hrSettings as [$rname, $label, $patterns])
                                    @php $active = request()->routeIs(...$patterns); @endphp
                                    <a href="{{ route($rname) }}"
                                       class="flex items-center gap-2 px-3 py-2 text-sm rounded-md transition
                                              {{ $active
                                                ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-900/40 dark:text-brand-300'
                                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                            {!! $icons[$rname] ?? '' !!}
                                        </svg>
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </nav>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                {{-- Portal hub --}}
                @if (request()->getHost() !== config('domains.portal'))
                <a href="{{ route('portal.home') }}" title="{{ __('Portal') }}"
                   class="inline-flex items-center justify-center h-9 w-9 rounded-md
                          bg-slate-100 text-slate-700 hover:bg-slate-200
                          dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                </a>
                @endif

                {{-- Language toggle --}}
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

                {{-- Theme toggle --}}
                <a href="{{ route('preferences.theme', $otherTheme) }}"
                   title="{{ __('Toggle theme') }}"
                   class="inline-flex items-center justify-center h-9 w-9 rounded-md
                          bg-slate-100 text-slate-700 hover:bg-slate-200
                          dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition">
                    @if ($theme === 'dark')
                        {{-- Sun icon --}}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                            <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                        </svg>
                    @else
                        {{-- Moon icon --}}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                        </svg>
                    @endif
                </a>

                @can('sync.run')
                <form action="{{ route('sync') }}" method="POST" class="hidden sm:block">
                    @csrf
                    <input type="hidden" name="model" value="all">
                    <button type="submit" title="{{ __('Sync with Odoo') }}"
                            class="inline-flex items-center gap-2 h-9 px-3 rounded-md text-xs font-medium
                                   bg-slate-100 text-slate-700 hover:bg-slate-200
                                   dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                            <path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>
                        </svg>
                        {{ __('Sync') }}
                    </button>
                </form>
                @endcan

                <div class="relative" id="user-menu-wrap">
                    <button type="button" id="user-menu-button"
                            aria-haspopup="true" aria-expanded="false" aria-label="{{ __('Account menu') }}"
                            class="inline-flex items-center gap-1 h-9 ps-1 pe-2 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 transition">
                        <span class="grid place-items-center w-7 h-7 rounded-full bg-brand-600 text-white text-xs font-bold">
                            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                        </span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ico-sm text-slate-400">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div id="user-menu"
                         class="hidden absolute end-0 mt-2 w-72 rounded-lg bg-white dark:bg-slate-900 shadow-soft ring-1 ring-slate-200 dark:ring-slate-800 z-30">
                        <div class="p-3 border-b border-slate-100 dark:border-slate-800">
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $user->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $user->email }}</p>
                        </div>
                        @if (!empty($user->roles))
                        <div class="p-3 border-b border-slate-100 dark:border-slate-800">
                            <p class="text-[10px] uppercase tracking-wide text-slate-400 mb-1.5">{{ __('Roles') }}</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($user->roles as $r)
                                    @php [$rLabel, $rColor] = $rolesPretty[$r] ?? [$r, 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700']; @endphp
                                    <span class="inline-flex items-center text-[11px] px-2 py-0.5 rounded-full ring-1 {{ $rColor }}">
                                        {{ $rLabel }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        <form action="{{ route('logout') }}" method="POST" class="p-1">
                            @csrf
                            <button type="submit" class="w-full {{ $dir === 'rtl' ? 'text-right' : 'text-left' }} px-3 py-2 text-sm rounded text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30 flex items-center gap-2">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>
                                </svg>
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if ($nav)
        <nav class="flex md:hidden gap-1 pb-3 overflow-x-auto">
            @foreach ($nav as [$rname, $label, $gate, $patterns])
                @if (!$gate || $user->can($gate))
                    @php $active = request()->routeIs(...$patterns); @endphp
                    <a href="{{ route($rname) }}"
                       class="inline-flex items-center gap-1.5 shrink-0 px-2.5 h-8 rounded-md text-xs
                              {{ $active
                                ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-900/40 dark:text-brand-300'
                                : 'text-slate-600 bg-slate-100 dark:text-slate-300 dark:bg-slate-800' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                            {!! $icons[$rname] ?? '' !!}
                        </svg>
                        {{ $label }}
                    </a>
                @endif
            @endforeach
            {{-- Settings entries stay flat chips on mobile — no room for a dropdown --}}
            @foreach ($hrSettings as [$rname, $label, $patterns])
                @php $active = request()->routeIs(...$patterns); @endphp
                <a href="{{ route($rname) }}"
                   class="inline-flex items-center gap-1.5 shrink-0 px-2.5 h-8 rounded-md text-xs
                          {{ $active
                            ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-900/40 dark:text-brand-300'
                            : 'text-slate-600 bg-slate-100 dark:text-slate-300 dark:bg-slate-800' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                        {!! $icons[$rname] ?? '' !!}
                    </svg>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        @endif
    </div>
</header>
@endif
@endauth

<main class="max-w-7xl mx-auto px-4 py-6">
    @if (session('status'))
        <div class="mb-4 flex items-start gap-3 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico mt-0.5 text-emerald-500">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>
            </svg>
            <p class="text-sm">{{ session('status') }}</p>
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 flex items-start gap-3 p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico mt-0.5 text-rose-500">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
            </svg>
            <ul class="text-sm space-y-1">
                @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif
    @yield('content')
</main>

@auth
<script>
    (function () {
        function dropdown(wrapId, btnId, menuId) {
            const wrap = document.getElementById(wrapId);
            const btn  = document.getElementById(btnId);
            const menu = document.getElementById(menuId);
            if (!wrap || !btn || !menu) return;

            function setOpen(open) {
                menu.classList.toggle('hidden', !open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                setOpen(menu.classList.contains('hidden'));
            });
            document.addEventListener('click', function (e) {
                if (!wrap.contains(e.target)) setOpen(false);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') setOpen(false);
            });
        }
        dropdown('user-menu-wrap', 'user-menu-button', 'user-menu');
        dropdown('hr-settings-wrap', 'hr-settings-button', 'hr-settings-menu');
    })();
</script>
@endauth
</body>
</html>
