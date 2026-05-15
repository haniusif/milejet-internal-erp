@php
    $locale = app()->getLocale();
    $dir    = $locale === 'ar' ? 'rtl' : 'ltr';
    $theme  = session('theme', 'light');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}" class="{{ $theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('HR System'))</title>
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
                        brand: {
                            50:  '#f5f3ff', 100: '#ede9fe', 200: '#ddd6fe',
                            300: '#c4b5fd', 400: '#a78bfa', 500: '#8b5cf6',
                            600: '#7c3aed', 700: '#6d28d9', 800: '#5b21b6',
                            900: '#4c1d95',
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
@php
    $icons = [
        'dashboard'     => '<path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/>',
        'employees.*'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'departments.*' => '<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/>',
        'leaves.*'      => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'attendances.*' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'contracts.*'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'payslips.*'    => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
    ];
    $nav = [
        'dashboard'     => [__('Dashboard'),   null],
        'employees.*'   => [__('Employees'),   null],
        'departments.*' => [__('Departments'), null],
        'leaves.*'      => [__('Leaves'),      null],
        'attendances.*' => [__('Attendance'),  null],
        'contracts.*'   => [__('Contracts'),   'contracts.view'],
        'payslips.*'    => [__('Payslips'),    'payslips.view'],
    ];
    $routes = [
        'dashboard'     => 'dashboard',
        'employees.*'   => 'employees.index',
        'departments.*' => 'departments.index',
        'leaves.*'      => 'leaves.index',
        'attendances.*' => 'attendances.index',
        'contracts.*'   => 'contracts.index',
        'payslips.*'    => 'payslips.index',
    ];
    $user = Auth::user();
    $rolesPretty = [
        'admin'           => [__('Role: System Admin'),     'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-900/40 dark:text-rose-300 dark:ring-rose-800'],
        'hr_manager'      => [__('Role: HR Manager'),       'bg-brand-50 text-brand-700 ring-brand-200 dark:bg-brand-900/40 dark:text-brand-300 dark:ring-brand-800'],
        'hr_officer'      => [__('Role: HR Officer'),       'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-300 dark:ring-indigo-800'],
        'payroll_manager' => [__('Role: Payroll Manager'),  'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-800'],
        'payroll_officer' => [__('Role: Payroll Officer'),  'bg-teal-50 text-teal-700 ring-teal-200 dark:bg-teal-900/40 dark:text-teal-300 dark:ring-teal-800'],
        'leave_manager'   => [__('Role: Leave Manager'),    'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800'],
        'employee'        => [__('Role: Employee'),         'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
    ];
    $otherLocale = $locale === 'ar' ? 'en' : 'ar';
    $otherTheme  = $theme === 'dark' ? 'light' : 'dark';
@endphp

<header class="sticky top-0 z-20 bg-white/85 dark:bg-slate-900/85 backdrop-blur border-b border-slate-200 dark:border-slate-800">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-slate-900 dark:text-slate-100 {{ $dir === 'rtl' ? 'ml-4' : 'mr-4' }} group">
                    <span class="grid place-items-center w-9 h-9 rounded-lg bg-gradient-to-br from-brand-600 to-brand-800 text-white shadow-soft">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="ico">
                            <path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>
                        </svg>
                    </span>
                    <span class="text-base leading-none tracking-tight">
                        MileJet <span class="text-brand-600 dark:text-brand-400">HR</span>
                    </span>
                </a>
                <nav class="hidden md:flex items-center gap-0.5">
                    @foreach ($nav as $pattern => [$label, $gate])
                        @if (!$gate || $user->can($gate))
                            @php $active = request()->routeIs($pattern); @endphp
                            <a href="{{ route($routes[$pattern]) }}"
                               class="inline-flex items-center gap-2 px-3 h-9 rounded-md text-sm transition
                                      {{ $active
                                        ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-900/40 dark:text-brand-300'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                    {!! $icons[$pattern] ?? '' !!}
                                </svg>
                                <span>{{ $label }}</span>
                            </a>
                        @endif
                    @endforeach
                </nav>
            </div>

            <div class="flex items-center gap-2">
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

                <div class="relative group">
                    <button type="button"
                            class="inline-flex items-center gap-2 h-9 ps-1 pe-3 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 transition">
                        <span class="grid place-items-center w-7 h-7 rounded-full bg-brand-600 text-white text-xs font-bold">
                            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                        </span>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $user->name }}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ico-sm text-slate-400">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="absolute end-0 mt-1 w-72 origin-top-right rounded-lg bg-white dark:bg-slate-900 shadow-soft ring-1 ring-slate-200 dark:ring-slate-800
                                opacity-0 invisible group-hover:opacity-100 group-hover:visible focus-within:opacity-100 focus-within:visible
                                transition z-30">
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

        <nav class="flex md:hidden gap-1 pb-3 overflow-x-auto">
            @foreach ($nav as $pattern => [$label, $gate])
                @if (!$gate || $user->can($gate))
                    @php $active = request()->routeIs($pattern); @endphp
                    <a href="{{ route($routes[$pattern]) }}"
                       class="inline-flex items-center gap-1.5 shrink-0 px-2.5 h-8 rounded-md text-xs
                              {{ $active
                                ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-900/40 dark:text-brand-300'
                                : 'text-slate-600 bg-slate-100 dark:text-slate-300 dark:bg-slate-800' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                            {!! $icons[$pattern] ?? '' !!}
                        </svg>
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </nav>
    </div>
</header>
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
</body>
</html>
