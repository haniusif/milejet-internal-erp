@extends('layouts.app')
@section('title', __('Fleet'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Fleet') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">
            {{ __('Vehicles') }} <span class="text-slate-400 font-medium">({{ $vehicles->total() }})</span>
        </h1>
    </div>
    @can('fleet.write')
    <a href="{{ route('fleet.vehicles.create') }}"
       class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition shadow-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        {{ __('New vehicle') }}
    </a>
    @endcan
</div>


{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    @php $cards = [
        [__('Vehicles'),         number_format($stats['total']),         'text-brand-700 dark:text-brand-300'],
        [__('Assigned'),         number_format($stats['assigned']),      'text-emerald-700 dark:text-emerald-300'],
        [__('Unassigned'),       number_format($stats['unassigned']),    'text-amber-700 dark:text-amber-300'],
        [__('Open services'),    number_format($stats['services_open']), 'text-sky-700 dark:text-sky-300'],
    ]; @endphp
    @foreach ($cards as [$label, $value, $color])
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">{{ $label }}</p>
            <p class="text-2xl font-bold tracking-tight tabular-nums {{ $color }}">{{ $value }}</p>
        </div>
    @endforeach
</div>

<form method="GET" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
    <div class="relative flex-1 min-w-[180px]">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             class="ico-sm absolute {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 text-slate-400">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by plate, model, or driver...') }}"
               class="w-full h-9 {{ app()->getLocale() === 'ar' ? 'pe-9 ps-3' : 'ps-9 pe-3' }} border border-slate-200 dark:border-slate-700 rounded-md text-sm
                      bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                      focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:focus:ring-brand-900 focus:outline-none">
    </div>
    <select name="state_id" onchange="this.form.submit()"
            class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
        <option value="">{{ __('All states') }}</option>
        @foreach ($states as $s)
            <option value="{{ $s->odoo_id }}" @selected(request('state_id') == $s->odoo_id)>{{ $s->name }}</option>
        @endforeach
    </select>
    <button class="h-9 px-4 rounded-md bg-slate-900 dark:bg-slate-700 text-white text-sm">{{ __('Filter') }}</button>
</form>

<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <th class="text-start font-medium px-4 py-3">{{ __('Vehicle') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('License plate') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Driver') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Odometer') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Fuel') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('State') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($vehicles as $v)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition cursor-pointer"
                        onclick="window.location='{{ route('fleet.vehicles.show', $v->id) }}'">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ $v->model_name ?: $v->name }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">{{ $v->model_year ?: '' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200" dir="ltr">
                                {{ $v->license_plate ?: '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $v->driver_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300 tabular-nums">
                            {{ number_format($v->odometer) }} <span class="text-xs text-slate-400">{{ $v->odometer_unit }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $v->fuel_type ? __(ucfirst(str_replace('_', ' ', $v->fuel_type))) : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $v->stateColor() }}">
                                {{ $v->state_name ?: '—' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-12 text-sm text-slate-400 dark:text-slate-500">{{ __('No vehicles found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $vehicles->links() }}</div>
@endsection
