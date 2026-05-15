@extends('layouts.app')
@section('title', 'العقود')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400">عقود التوظيف</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">العقود</h1>
    </div>
    <div class="flex items-center gap-2 text-xs">
        <div class="px-3 py-1.5 rounded-md bg-white border border-slate-200">
            <span class="text-slate-400">الإجمالي:</span>
            <span class="font-semibold text-slate-900 tabular-nums ms-1">{{ $totals['count'] }}</span>
        </div>
        <div class="px-3 py-1.5 rounded-md bg-emerald-50 border border-emerald-200">
            <span class="text-emerald-600">سارية:</span>
            <span class="font-semibold text-emerald-900 tabular-nums ms-1">{{ $totals['open'] }}</span>
        </div>
        <div class="px-3 py-1.5 rounded-md bg-brand-50 border border-brand-200">
            <span class="text-brand-600">مجموع الرواتب:</span>
            <span class="font-semibold text-brand-900 tabular-nums ms-1">{{ number_format($totals['total_wage'], 0) }}</span>
            <span class="text-brand-500 text-[10px] ms-0.5">ر.س</span>
        </div>
    </div>
</div>

<form method="GET" class="bg-white border border-slate-200 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
    <div class="relative flex-1 min-w-[180px]">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="ابحث باسم العقد أو الموظف..."
               class="w-full h-9 ps-3 pe-9 border border-slate-200 rounded-md text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 focus:outline-none">
    </div>
    <select name="employee_id" class="h-9 px-3 border border-slate-200 rounded-md text-sm bg-white">
        <option value="">جميع الموظفين</option>
        @foreach ($employees as $emp)
            <option value="{{ $emp->odoo_id }}" @selected(request('employee_id') == $emp->odoo_id)>{{ $emp->name }}</option>
        @endforeach
    </select>
    <select name="state" class="h-9 px-3 border border-slate-200 rounded-md text-sm bg-white">
        <option value="">جميع الحالات</option>
        <option value="draft"   @selected(request('state')=='draft')>مسودة</option>
        <option value="open"    @selected(request('state')=='open')>سارٍ</option>
        <option value="pending" @selected(request('state')=='pending')>بانتظار التجديد</option>
        <option value="close"   @selected(request('state')=='close')>منتهٍ</option>
        <option value="cancel"  @selected(request('state')=='cancel')>ملغى</option>
    </select>
    <button class="h-9 px-4 bg-slate-900 text-white text-sm font-medium rounded-md hover:bg-slate-800">تصفية</button>
</form>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-4 py-3 text-right font-medium">المرجع</th>
                <th class="px-4 py-3 text-right font-medium">الموظف</th>
                <th class="px-4 py-3 text-right font-medium">الراتب</th>
                <th class="px-4 py-3 text-right font-medium">من</th>
                <th class="px-4 py-3 text-right font-medium">إلى</th>
                <th class="px-4 py-3 text-right font-medium">الهيكل</th>
                <th class="px-4 py-3 text-right font-medium">الحالة</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($contracts as $contract)
            <tr class="hover:bg-slate-50/60 transition">
                <td class="px-4 py-3 text-xs text-slate-500">{{ $contract->name }}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="grid place-items-center w-7 h-7 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-white text-[10px] font-bold flex-shrink-0">
                            {{ mb_strtoupper(mb_substr($contract->employee_name, 0, 1)) }}
                        </span>
                        <span class="font-medium text-slate-900">{{ $contract->employee_name }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 font-semibold text-brand-700 tabular-nums">
                    {{ number_format($contract->wage, 2) }} <span class="text-[10px] text-slate-400">ر.س</span>
                </td>
                <td class="px-4 py-3 text-slate-600 tabular-nums">{{ $contract->date_start?->format('Y-m-d') ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600 tabular-nums">{{ $contract->date_end?->format('Y-m-d') ?? '—' }}</td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ $contract->struct_name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full {{ $contract->stateColor() }}">
                        {{ $contract->stateLabel() }}
                    </span>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">لا توجد عقود.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $contracts->links() }}</div>
@endsection
