@php
    $kids = $byParent->get($employee->odoo_id, collect());
@endphp
<li>
    <a href="{{ route('employees.show', $employee->id) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
       class="group flex flex-col items-center w-40 px-3 py-3 rounded-xl border border-slate-200 dark:border-slate-800
              bg-white dark:bg-slate-900 hover:border-brand-300 dark:hover:border-brand-700 hover:shadow-soft transition text-center">
        @if ($employee->avatar_data_uri)
            <img src="{{ $employee->avatar_data_uri }}" alt=""
                 class="w-12 h-12 rounded-full object-cover ring-1 ring-slate-200 dark:ring-slate-700 mb-2">
        @else
            <span class="grid place-items-center w-12 h-12 rounded-full bg-brand-600 text-white text-sm font-bold mb-2">
                {{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}
            </span>
        @endif
        <span class="text-[13px] font-semibold text-slate-900 dark:text-slate-100 leading-tight line-clamp-2">{{ $employee->name }}</span>
        @if ($employee->job_title)
            <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-tight line-clamp-2">{{ $employee->job_title }}</span>
        @endif
        @if ($employee->department_name)
            <span class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5 truncate max-w-full">{{ $employee->department_name }}</span>
        @endif
        @if ($kids->isNotEmpty())
            <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 mt-1.5 px-1.5 rounded-full
                         bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300 text-[10px] font-bold"
                  title="{{ __('Direct reports') }}">
                {{ $kids->count() }}
            </span>
        @endif
    </a>
    @if ($kids->isNotEmpty())
        <ul>
            @foreach ($kids as $kid)
                @include('employees._org_node', ['employee' => $kid])
            @endforeach
        </ul>
    @endif
</li>
