@extends('layouts.app')
@section('title', __('Dashboard'))

@section('content')
    @php
        $locale = app()->getLocale();
        $dir    = $locale === 'ar' ? 'rtl' : 'ltr';
        $dateStr = $locale === 'ar'
            ? \Carbon\Carbon::now()->locale('ar')->isoFormat('dddd، D MMMM Y')
            : \Carbon\Carbon::now()->locale('en')->isoFormat('dddd, D MMMM Y');
    @endphp

    {{-- Hero header --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Dashboard') }}</p>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">
                {{ __('Welcome, :name', ['name' => explode(' ', Auth::user()->name)[0]]) }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $dateStr }}</p>
        </div>
        @if ($lastSync)
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            {{ __('Last sync :time', ['time' => $lastSync->completed_at?->locale($locale)->diffForHumans()]) }}
        </div>
        @endif
    </div>

    {{-- Stats grid --}}
    @php
        $cards = [
            ['employees.index',  [],                    __('Employees'),         $stats['employees'],
                'from-indigo-500 to-indigo-700',
                '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ['departments.index', [],                   __('Departments'),       $stats['departments'],
                'from-emerald-500 to-emerald-700',
                '<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/>'],
            ['leaves.index',     ['state'=>'confirm'],  __('Pending leaves'),    $stats['pending_leaves'],
                'from-amber-500 to-amber-600',
                '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
            ['leaves.index',     ['state'=>'validate'], __('Approved leaves'),   $stats['approved_leaves'],
                'from-sky-500 to-sky-700',
                '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>'],
            ['attendances.index', [],                   __("Today's attendance"), $stats['today_attendance'],
                'from-brand-500 to-brand-700',
                '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
        ];
        $chevron = $dir === 'rtl'
            ? '<path d="m15 18-6-6 6-6"/>'
            : '<path d="m9 18 6-6-6-6"/>';
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        @foreach ($cards as [$route, $params, $label, $value, $grad, $iconPath])
            <a href="{{ route($route, $params) }}"
               class="group relative overflow-hidden rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 transition
                      hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-soft">
                <div class="flex items-start justify-between mb-3">
                    <span class="grid place-items-center w-9 h-9 rounded-lg bg-gradient-to-br {{ $grad }} text-white shadow-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" class="ico">
                            {!! $iconPath !!}
                        </svg>
                    </span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"
                         class="ico-sm text-slate-300 dark:text-slate-600 group-hover:text-slate-500 dark:group-hover:text-slate-400 group-hover:{{ $dir === 'rtl' ? '-translate-x-1' : 'translate-x-1' }} transition">
                        {!! $chevron !!}
                    </svg>
                </div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ $label }}</p>
                <p class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 leading-none">
                    {{ number_format($value) }}
                </p>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Quick actions (2/3 width) --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Quick actions') }}</h2>
                <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('Shortcuts for the most common operations') }}</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @php
                    $actions = [
                        ['employees.write',   'employees.create',   __('Add employee'),    'text-indigo-600 dark:text-indigo-400',   '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'],
                        ['departments.write', 'departments.create', __('Add department'),  'text-emerald-600 dark:text-emerald-400', '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'],
                        [null,                'leaves.create',      __('Request leave'),   'text-amber-600 dark:text-amber-400',     '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
                        [null,                'attendances.index',  __('Record attendance'), 'text-brand-600 dark:text-brand-400',   '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
                        ['payslips.create',   'payslips.create',    __('Create payslip'),  'text-emerald-700 dark:text-emerald-400', '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/>'],
                        ['contracts.view',    'contracts.index',    __('View contracts'),  'text-slate-600 dark:text-slate-300',     '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'],
                    ];
                @endphp
                @foreach ($actions as [$gate, $routeName, $label, $color, $iconPath])
                    @if (!$gate || auth()->user()->can($gate))
                        <a href="{{ route($routeName) }}"
                           class="group flex flex-col items-start gap-2 p-3 rounded-lg border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                            <span class="grid place-items-center w-8 h-8 rounded-md bg-slate-50 dark:bg-slate-800 {{ $color }} group-hover:bg-white dark:group-hover:bg-slate-900">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">{!! $iconPath !!}</svg>
                            </span>
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ $label }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Pending leaves (1/3 width) --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Leaves awaiting approval') }}</h2>
                <span class="inline-flex items-center justify-center min-w-[1.5rem] h-5 text-[11px] font-bold rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 px-1.5">
                    {{ $recentLeaves->count() }}
                </span>
            </div>
            @forelse ($recentLeaves as $leave)
                <a href="{{ route('leaves.index', ['state'=>'confirm']) }}"
                   class="flex items-start gap-3 py-2.5 border-b last:border-0 border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 -mx-2 px-2 rounded transition">
                    <span class="grid place-items-center w-8 h-8 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-xs font-bold flex-shrink-0">
                        {{ mb_strtoupper(mb_substr($leave->employee_name, 0, 1)) }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ $leave->employee_name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $leave->date_from->format('Y-m-d') }} → {{ $leave->date_to->format('Y-m-d') }}
                            <span class="mx-1">·</span>
                            {{ trans_choice(':count day', $leave->number_of_days, ['count' => $leave->number_of_days]) }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="text-center py-8 text-sm text-slate-400 dark:text-slate-500">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 mx-auto mb-2 text-slate-200 dark:text-slate-700">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>
                    </svg>
                    {{ __('No pending requests') }}
                </div>
            @endforelse
        </div>
    </div>
@endsection
