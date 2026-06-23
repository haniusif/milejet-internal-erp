@extends('layouts.app')
@section('title', __('Countries'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <a href="{{ route('settings.index') }}" class="text-xs uppercase tracking-wider text-slate-400 hover:text-brand-600 transition">
            {{ __('Settings') }}
        </a>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">
            {{ __('Countries') }} <span class="text-slate-400 font-medium">({{ $countries->count() }})</span>
        </h1>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search countries…') }}"
               class="h-9 w-56 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900
                      text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400
                      focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
    </form>
</div>

<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3 text-start font-medium">#</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Name') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Code') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Phone code') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Currency') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Employees') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($countries as $c)
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3 text-xs text-slate-400 tabular-nums">{{ $c->odoo_id }}</td>
                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $c->name }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 h-6 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {{ $c->code ?? '—' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300 tabular-nums">{{ $c->phone_code ? '+'.$c->phone_code : '—' }}</td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $c->currency_name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($c->employees_count > 0)
                            <span class="inline-flex items-center justify-center min-w-[1.75rem] h-6 px-2 text-xs font-semibold rounded-full bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300 tabular-nums">
                                {{ $c->employees_count }}
                            </span>
                        @else
                            <span class="text-xs text-slate-300 dark:text-slate-600">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">
                    {{ $q !== '' ? __('No countries match your search.') : __('No countries found. Run a sync to import them from Odoo.') }}
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
