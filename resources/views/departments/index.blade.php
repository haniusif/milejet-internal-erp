@extends('layouts.app')
@section('title', 'الأقسام')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400">الهيكل التنظيمي</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
            الأقسام <span class="text-slate-400 font-medium">({{ $departments->count() }})</span>
        </h1>
    </div>
    @can('departments.write')
    <a href="{{ route('departments.create') }}"
       class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition shadow-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        قسم جديد
    </a>
    @endcan
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-4 py-3 text-right font-medium">#</th>
                <th class="px-4 py-3 text-right font-medium">الاسم</th>
                <th class="px-4 py-3 text-right font-medium">القسم الرئيسي</th>
                <th class="px-4 py-3 text-right font-medium">المدير</th>
                <th class="px-4 py-3 text-right font-medium">الموظفون</th>
                <th class="px-4 py-3 text-left font-medium">إجراءات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($departments as $d)
                <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-4 py-3 text-xs text-slate-400 tabular-nums">{{ $d->odoo_id }}</td>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $d->name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $d->parent_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $d->manager_name ?: '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center justify-center min-w-[1.75rem] h-6 px-2 text-xs font-semibold rounded-full bg-slate-100 text-slate-700 tabular-nums">
                            {{ $d->total_employee }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-left whitespace-nowrap">
                        @can('departments.write')
                            <a href="{{ route('departments.edit', $d->id) }}"
                               class="inline-flex items-center text-xs text-brand-600 hover:text-brand-700 hover:underline">تعديل</a>
                        @endcan
                        @can('departments.delete')
                            <form action="{{ route('departments.destroy', $d->id) }}" method="POST" class="inline ms-2"
                                  onsubmit="return confirm('حذف القسم؟')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-rose-600 hover:text-rose-700 hover:underline">حذف</button>
                            </form>
                        @endcan
                        @cannot('departments.write')
                            <span class="text-xs text-slate-400">عرض فقط</span>
                        @endcannot
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">لا توجد أقسام.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
