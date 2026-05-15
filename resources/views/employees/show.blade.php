@extends('layouts.app')
@section('title', $employee->name)

@section('content')
<a href="{{ route('employees.index') }}"
   class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
        <path d="m9 18 6-6-6-6"/>
    </svg>
    العودة إلى قائمة الموظفين
</a>

{{-- Hero profile card --}}
<div class="bg-white border border-slate-200 rounded-xl overflow-hidden mb-5">
    <div class="h-24 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-900"></div>
    <div class="px-6 pb-5">
        <div class="flex flex-wrap items-end justify-between gap-4 -mt-12">
            <div class="flex items-end gap-4">
                @if ($extra['image'])
                    <img src="data:image/png;base64,{{ $extra['image'] }}"
                         alt="{{ $employee->name }}"
                         class="w-24 h-24 rounded-2xl object-cover ring-4 ring-white shadow-soft bg-white">
                @else
                    <span class="grid place-items-center w-24 h-24 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white text-3xl font-bold ring-4 ring-white shadow-soft">
                        {{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}
                    </span>
                @endif
                <div class="pb-2">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $employee->name }}</h1>
                    <p class="text-sm text-slate-500 mt-0.5">
                        {{ $employee->job_title ?: 'لا يوجد مسمى وظيفي' }}
                        @if ($extra['identification_id'])
                            <span class="mx-2 text-slate-300">·</span>
                            <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $extra['identification_id'] }}</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 pb-2">
                @if ($employee->active)
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        نشط
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 ring-1 ring-slate-200">
                        غير نشط
                    </span>
                @endif
                @can('employees.write')
                <a href="{{ route('employees.edit', $employee->id) }}"
                   class="inline-flex items-center gap-1.5 h-9 px-4 rounded-md bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    تعديل
                </a>
                @endcan
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    {{-- Left column: contact + personal --}}
    <div class="space-y-5">
        {{-- Contact info --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                معلومات التواصل
            </h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">البريد الإلكتروني</dt>
                    <dd class="text-slate-900">
                        @if ($employee->work_email)
                            <a href="mailto:{{ $employee->work_email }}" class="text-brand-600 hover:underline">{{ $employee->work_email }}</a>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">هاتف العمل</dt>
                    <dd class="text-slate-900 tabular-nums">{{ $employee->work_phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">الجوال</dt>
                    <dd class="text-slate-900 tabular-nums">{{ $employee->mobile_phone ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Personal info --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                معلومات شخصية
            </h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">الجنسية</dt>
                    <dd class="text-slate-900">{{ $extra['country_name'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">تاريخ الميلاد</dt>
                    <dd class="text-slate-900 tabular-nums">
                        @if ($extra['birthday'])
                            {{ \Carbon\Carbon::parse($extra['birthday'])->format('Y-m-d') }}
                            <span class="text-xs text-slate-500 ms-2">
                                ({{ \Carbon\Carbon::parse($extra['birthday'])->age }} سنة)
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">الجنس</dt>
                    <dd class="text-slate-900">
                        {{ match ($extra['gender']) {
                            'male'   => 'ذكر',
                            'female' => 'أنثى',
                            'other'  => 'غير ذلك',
                            default  => '—',
                        } }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Organization --}}
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                    <path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/>
                </svg>
                التنظيم
            </h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">القسم</dt>
                    <dd class="text-slate-900">{{ $employee->department_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 mb-0.5">المدير المباشر</dt>
                    <dd class="text-slate-900">{{ $employee->parent_name ?: '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Right column (2/3): contract + activity --}}
    <div class="lg:col-span-2 space-y-5">
        {{-- Contract --}}
        @if ($contract)
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                    </svg>
                    العقد الحالي
                </h2>
                <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full {{ $contract->stateColor() }}">
                    {{ $contract->stateLabel() }}
                </span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-slate-400 mb-1">الراتب الإجمالي</p>
                    <p class="text-2xl font-bold tracking-tight text-brand-700 tabular-nums">
                        {{ number_format($contract->wage, 0) }}
                        <span class="text-xs text-slate-400 font-normal">ر.س</span>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">تاريخ البدء</p>
                    <p class="text-sm font-medium text-slate-900 tabular-nums">
                        {{ $contract->date_start?->format('Y-m-d') ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">تاريخ الانتهاء</p>
                    <p class="text-sm font-medium text-slate-900 tabular-nums">
                        {{ $contract->date_end?->format('Y-m-d') ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">المرجع</p>
                    <p class="text-sm font-medium text-slate-700 truncate">{{ $contract->name }}</p>
                </div>
            </div>
        </div>
        @else
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 flex items-center gap-2">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>
            </svg>
            لا يوجد عقد لهذا الموظف.
        </div>
        @endif

        {{-- Recent payslips --}}
        @if (auth()->user()->can('payslips.view'))
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-emerald-600">
                    <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/>
                </svg>
                آخر قسائم الراتب
            </h2>
            @forelse ($recentPayslips as $p)
                <a href="{{ route('payslips.show', $p->id) }}"
                   class="flex items-center justify-between py-2.5 border-b last:border-0 border-slate-100 hover:bg-slate-50 -mx-2 px-2 rounded transition">
                    <div class="flex items-center gap-3">
                        <span class="grid place-items-center w-8 h-8 rounded-md bg-emerald-50 text-emerald-700">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/>
                            </svg>
                        </span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $p->number ?? 'قسيمة #' . $p->id }}</p>
                            <p class="text-xs text-slate-500 tabular-nums">
                                {{ $p->date_from?->format('Y-m-d') }} → {{ $p->date_to?->format('Y-m-d') }}
                            </p>
                        </div>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-bold text-emerald-700 tabular-nums">{{ number_format($p->net_total, 2) }}</p>
                        <span class="inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full {{ $p->stateColor() }}">
                            {{ $p->stateLabel() }}
                        </span>
                    </div>
                </a>
            @empty
                <p class="text-sm text-slate-400 text-center py-4">لا توجد قسائم رواتب.</p>
            @endforelse
        </div>
        @endif

        {{-- Recent leaves & attendance side by side --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-amber-600">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    آخر الإجازات
                </h2>
                @forelse ($recentLeaves as $leave)
                    <div class="flex items-center justify-between py-2 border-b last:border-0 border-slate-100">
                        <div>
                            <p class="text-sm text-slate-900 tabular-nums">
                                {{ $leave->date_from?->format('Y-m-d') }}
                            </p>
                            <p class="text-xs text-slate-500">{{ $leave->number_of_days }} يوم · {{ $leave->leave_type_name ?? '—' }}</p>
                        </div>
                        <span class="inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full {{ $leave->stateColor() }}">
                            {{ $leave->stateLabel() }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-4">لا توجد إجازات.</p>
                @endforelse
            </div>

            <div class="bg-white border border-slate-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm text-brand-600">
                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                    </svg>
                    آخر سجلات الحضور
                </h2>
                @forelse ($recentAttendances as $att)
                    <div class="flex items-center justify-between py-2 border-b last:border-0 border-slate-100 text-sm">
                        <div>
                            <p class="text-slate-900 tabular-nums">{{ $att->check_in?->format('Y-m-d H:i') }}</p>
                            <p class="text-xs text-slate-500 tabular-nums">
                                @if ($att->check_out)
                                    خروج {{ $att->check_out->format('H:i') }} · {{ number_format($att->worked_hours, 2) }} ساعة
                                @else
                                    <span class="text-emerald-600 font-medium">حاضر الآن</span>
                                @endif
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-4">لا توجد سجلات حضور.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
