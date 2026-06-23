@extends('layouts.app')
@section('title', __('Configuration'))

@section('content')
<div class="mb-5">
    <a href="{{ route('settings.index') }}" class="text-xs uppercase tracking-wider text-slate-400 hover:text-brand-600 transition">
        {{ __('Settings') }}
    </a>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">{{ __('Configuration') }}</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Read-only view. Values are managed in the server .env file.') }}</p>
</div>

<div class="grid gap-4 lg:grid-cols-2">

    {{-- Odoo connection --}}
    <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 uppercase tracking-wider">{{ __('Odoo connection') }}</h2>
            @if ($odooStatus['ok'])
                <span class="inline-flex items-center gap-1.5 px-2 h-6 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ __('Connected') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2 h-6 text-xs font-semibold rounded-full bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300"
                      title="{{ $odooStatus['detail'] }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>{{ __('Unreachable') }}
                </span>
            @endif
        </div>
        <dl class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
            @foreach ([
                __('URL')        => $odooConfig['url'],
                __('Database')   => $odooConfig['db'],
                __('Service user') => $odooConfig['username'],
                __('API key')    => $odooConfig['api_key'],
                __('Verify SSL') => $odooConfig['verify_ssl'] ? __('Yes') : __('No'),
            ] as $label => $value)
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                    <dd class="font-medium text-slate-900 dark:text-slate-100 text-end break-all" dir="ltr">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Application --}}
    <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-card">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 uppercase tracking-wider mb-4">{{ __('Application') }}</h2>
        <dl class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
            @foreach ([
                __('Environment') => $app['env'],
                __('Debug mode')  => $app['debug'] ? __('On') : __('Off'),
                __('Timezone')    => $app['timezone'],
                __('Default locale') => strtoupper($app['locale']),
                __('Laravel')     => $app['laravel'],
                __('PHP')         => $app['php'],
            ] as $label => $value)
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                    <dd class="font-medium text-slate-900 dark:text-slate-100" dir="ltr">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Attendance --}}
    <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-card">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 uppercase tracking-wider mb-4">{{ __('Attendance & geofence') }}</h2>
        <dl class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
            <div class="flex items-center justify-between gap-4 py-2.5">
                <dt class="text-slate-500 dark:text-slate-400">{{ __('Default radius (m)') }}</dt>
                <dd class="font-medium text-slate-900 dark:text-slate-100 tabular-nums">{{ $attendance['geofence_radius'] }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2.5">
                <dt class="text-slate-500 dark:text-slate-400">{{ __('Enforce geofence') }}</dt>
                <dd>
                    @if ($attendance['geofence_enforce'])
                        <span class="inline-flex items-center px-2 h-6 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('Enabled') }}</span>
                    @else
                        <span class="inline-flex items-center px-2 h-6 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Disabled') }}</span>
                    @endif
                </dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-slate-400">{{ __('Per-office radius and coordinates are set under Offices; per-employee exemptions on the employee profile.') }}</p>
    </section>

    {{-- Domains --}}
    <section class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-card">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 uppercase tracking-wider mb-4">{{ __('Module domains') }}</h2>
        <dl class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
            @foreach ($domains as $module => $host)
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __("title.{$module}") }}</dt>
                    <dd class="font-medium" dir="ltr">
                        <a href="https://{{ $host }}" class="text-brand-600 hover:text-brand-700 hover:underline dark:text-brand-300">{{ $host }}</a>
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>

{{-- Sync status --}}
<section class="mt-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between px-5 pt-5 pb-3">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 uppercase tracking-wider">{{ __('Last sync per model') }}</h2>
        @can('sync.run')
        <form action="{{ route('sync') }}" method="POST">
            @csrf
            <input type="hidden" name="model" value="all">
            <button type="submit"
                    class="inline-flex items-center gap-2 h-8 px-3 rounded-md text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200
                           dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                    <path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>
                </svg>
                {{ __('Sync') }}
            </button>
        </form>
        @endcan
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <tr>
                <th class="px-5 py-3 text-start font-medium">{{ __('Model') }}</th>
                <th class="px-5 py-3 text-start font-medium">{{ __('Status') }}</th>
                <th class="px-5 py-3 text-start font-medium">{{ __('Records') }}</th>
                <th class="px-5 py-3 text-start font-medium">{{ __('Completed at') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($syncLogs as $log)
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3 font-medium text-slate-900 dark:text-slate-100" dir="ltr">{{ $log->model }}</td>
                    <td class="px-5 py-3">
                        @if ($log->status === 'success')
                            <span class="inline-flex items-center px-2 h-6 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('Success') }}</span>
                        @elseif ($log->status === 'failed')
                            <span class="inline-flex items-center px-2 h-6 text-xs font-semibold rounded-full bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300"
                                  title="{{ $log->error_message }}">{{ __('Failed') }}</span>
                        @else
                            <span class="inline-flex items-center px-2 h-6 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Running') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300 tabular-nums">{{ $log->records_synced ?? '—' }}</td>
                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300 tabular-nums" dir="ltr">
                        {{ $log->completed_at?->format('Y-m-d H:i') ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('No sync has run yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
@endsection
