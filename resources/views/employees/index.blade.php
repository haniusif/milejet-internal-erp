@extends('layouts.app')
@section('title', 'الموظفون')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400">الموارد البشرية</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
            الموظفون <span class="text-slate-400 font-medium">({{ $employees->total() }})</span>
        </h1>
    </div>
    @can('employees.write')
    <a href="{{ route('employees.create') }}"
       class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition shadow-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        موظف جديد
    </a>
    @endcan
</div>

<form method="GET" class="bg-white border border-slate-200 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
    <div class="relative flex-1 min-w-[180px]">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             class="ico-sm absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="ابحث بالاسم، البريد، أو المسمى..."
               class="w-full h-9 ps-3 pe-9 border border-slate-200 rounded-md text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 focus:outline-none">
    </div>
    <select name="department_id" class="h-9 px-3 border border-slate-200 rounded-md text-sm bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-100 focus:outline-none">
        <option value="">جميع الأقسام</option>
        @foreach ($departments as $d)
            <option value="{{ $d->odoo_id }}" {{ request('department_id') == $d->odoo_id ? 'selected' : '' }}>{{ $d->name }}</option>
        @endforeach
    </select>
    <button class="h-9 px-4 bg-slate-900 text-white text-sm font-medium rounded-md hover:bg-slate-800 transition">
        تصفية
    </button>
    @if (request('q') || request('department_id'))
        <a href="{{ route('employees.index') }}" class="text-xs text-slate-500 hover:text-slate-700">إعادة تعيين</a>
    @endif
</form>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-4 py-3 text-right font-medium">#</th>
                <th class="px-4 py-3 text-right font-medium">الاسم</th>
                <th class="px-4 py-3 text-right font-medium">المسمى الوظيفي</th>
                <th class="px-4 py-3 text-right font-medium">القسم</th>
                <th class="px-4 py-3 text-right font-medium">المدير</th>
                <th class="px-4 py-3 text-right font-medium">البريد</th>
                <th class="px-4 py-3 text-left font-medium">إجراءات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($employees as $e)
                <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-4 py-3 text-xs text-slate-400 tabular-nums">{{ $e->odoo_id }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('employees.show', $e->id) }}" class="flex items-center gap-3 group">
                            @if ($e->image_small && strlen($e->image_small) > 1500)
                                <img src="data:image/png;base64,{{ $e->image_small }}"
                                     alt="{{ $e->name }}"
                                     class="w-8 h-8 rounded-full object-cover ring-1 ring-slate-200 flex-shrink-0 bg-white">
                            @else
                                <span class="grid place-items-center w-8 h-8 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-white text-xs font-bold flex-shrink-0">
                                    {{ mb_strtoupper(mb_substr($e->name, 0, 1)) }}
                                </span>
                            @endif
                            <span class="font-medium text-slate-900 group-hover:text-brand-700 group-hover:underline">{{ $e->name }}</span>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $e->job_title ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $e->department_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $e->parent_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $e->work_email ?: '—' }}</td>
                    <td class="px-4 py-3 text-left whitespace-nowrap">
                        <a href="{{ route('employees.show', $e->id) }}"
                           class="inline-flex items-center text-xs text-slate-600 hover:text-slate-900 hover:underline">عرض</a>
                        @can('employees.write')
                            <a href="{{ route('employees.edit', $e->id) }}"
                               class="inline-flex items-center text-xs text-brand-600 hover:text-brand-700 hover:underline ms-2">تعديل</a>
                        @endcan
                        @can('employees.delete')
                            <form action="{{ route('employees.destroy', $e->id) }}" method="POST" class="inline ms-2"
                                  onsubmit="return confirm('حذف الموظف؟')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-rose-600 hover:text-rose-700 hover:underline">حذف</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">لا يوجد موظفون.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $employees->links() }}</div>
@endsection
