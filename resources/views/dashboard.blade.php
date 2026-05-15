@extends('layouts.app')
@section('title', 'لوحة التحكم')

@section('content')
    {{-- Hero header --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-400">لوحة التحكم</p>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-slate-900 mt-1">
                مرحباً، {{ explode(' ', Auth::user()->name)[0] }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ \Carbon\Carbon::now()->locale('ar')->isoFormat('dddd، D MMMM Y') }}
            </p>
        </div>
        @if ($lastSync)
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md bg-white border border-slate-200 text-xs text-slate-500">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            آخر مزامنة {{ $lastSync->completed_at?->diffForHumans() }}
        </div>
        @endif
    </div>

    {{-- Stats grid --}}
    @php
        $cards = [
            ['employees.index', [], 'الموظفون', $stats['employees'],
                'from-indigo-500 to-indigo-700',
                '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ['departments.index', [], 'الأقسام', $stats['departments'],
                'from-emerald-500 to-emerald-700',
                '<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/>'],
            ['leaves.index', ['state'=>'confirm'], 'إجازات معلّقة', $stats['pending_leaves'],
                'from-amber-500 to-amber-600',
                '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
            ['leaves.index', ['state'=>'validate'], 'إجازات معتمدة', $stats['approved_leaves'],
                'from-sky-500 to-sky-700',
                '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>'],
            ['attendances.index', [], 'حضور اليوم', $stats['today_attendance'],
                'from-brand-500 to-brand-700',
                '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
        ];
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        @foreach ($cards as [$route, $params, $label, $value, $grad, $iconPath])
            <a href="{{ route($route, $params) }}"
               class="group relative overflow-hidden rounded-xl bg-white border border-slate-200 p-4 transition
                      hover:border-slate-300 hover:shadow-soft">
                <div class="flex items-start justify-between mb-3">
                    <span class="grid place-items-center w-9 h-9 rounded-lg bg-gradient-to-br {{ $grad }} text-white shadow-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" class="ico">
                            {!! $iconPath !!}
                        </svg>
                    </span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"
                         class="ico-sm text-slate-300 group-hover:text-slate-500 group-hover:-translate-x-1 transition">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                </div>
                <p class="text-xs font-medium text-slate-500 mb-1">{{ $label }}</p>
                <p class="text-3xl font-bold tracking-tight text-slate-900 leading-none">
                    {{ number_format($value) }}
                </p>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Quick actions (2/3 width) --}}
        <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-slate-900">إجراءات سريعة</h2>
                <span class="text-xs text-slate-400">اختصارات للعمليات الأكثر استخداماً</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @php
                    $actions = [
                        ['employees.write',   'employees.create',   'إضافة موظف',        'text-indigo-600',  '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'],
                        ['departments.write', 'departments.create', 'إضافة قسم',         'text-emerald-600', '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'],
                        [null,                'leaves.create',      'طلب إجازة',         'text-amber-600',   '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
                        [null,                'attendances.index',  'تسجيل حضور',        'text-brand-600',   '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
                        ['payslips.create',   'payslips.create',    'إنشاء قسيمة راتب', 'text-emerald-700', '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/>'],
                        ['contracts.view',    'contracts.index',    'عرض العقود',         'text-slate-600',   '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'],
                    ];
                @endphp
                @foreach ($actions as [$gate, $routeName, $label, $color, $iconPath])
                    @if (!$gate || auth()->user()->can($gate))
                        <a href="{{ route($routeName) }}"
                           class="group flex flex-col items-start gap-2 p-3 rounded-lg border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition">
                            <span class="grid place-items-center w-8 h-8 rounded-md bg-slate-50 {{ $color }} group-hover:bg-white">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">{!! $iconPath !!}</svg>
                            </span>
                            <span class="text-xs font-medium text-slate-700">{{ $label }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Pending leaves (1/3 width) --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-900">إجازات بحاجة لموافقة</h2>
                <span class="inline-flex items-center justify-center min-w-[1.5rem] h-5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-800 px-1.5">
                    {{ $recentLeaves->count() }}
                </span>
            </div>
            @forelse ($recentLeaves as $leave)
                <a href="{{ route('leaves.index', ['state'=>'confirm']) }}"
                   class="flex items-start gap-3 py-2.5 border-b last:border-0 border-slate-100 hover:bg-slate-50 -mx-2 px-2 rounded transition">
                    <span class="grid place-items-center w-8 h-8 rounded-full bg-amber-50 text-amber-700 text-xs font-bold flex-shrink-0">
                        {{ mb_strtoupper(mb_substr($leave->employee_name, 0, 1)) }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-900 truncate">{{ $leave->employee_name }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ $leave->date_from->format('Y-m-d') }} → {{ $leave->date_to->format('Y-m-d') }}
                            <span class="mx-1">·</span>
                            {{ $leave->number_of_days }} يوم
                        </p>
                    </div>
                </a>
            @empty
                <div class="text-center py-8 text-sm text-slate-400">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 mx-auto mb-2 text-slate-200">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>
                    </svg>
                    لا توجد طلبات معلّقة
                </div>
            @endforelse
        </div>
    </div>
@endsection
