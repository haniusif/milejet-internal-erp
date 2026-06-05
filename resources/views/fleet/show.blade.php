@extends('layouts.app')
@section('title', $vehicle->name)

@section('content')
<a href="{{ route('fleet.index') }}"
   class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 mb-4">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
        @if (app()->getLocale() === 'ar')
            <path d="m9 18 6-6-6-6"/>
        @else
            <path d="m15 18-6-6 6-6"/>
        @endif
    </svg>
    {{ __('Back to vehicles') }}
</a>

{{-- Header card --}}
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 mb-5 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <span class="grid place-items-center w-14 h-14 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 text-white">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7">
                <path d="M5 17h-2v-6l2-5h9l4 5h3a2 2 0 0 1 2 2v4h-2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/>
            </svg>
        </span>
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $vehicle->model_name ?: $vehicle->name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 flex items-center gap-2 mt-0.5">
                @if ($vehicle->license_plate)
                    <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200" dir="ltr">{{ $vehicle->license_plate }}</span>
                @endif
                <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $vehicle->stateColor() }}">{{ $vehicle->state_name ?: '—' }}</span>
            </p>
        </div>
    </div>
    @can('fleet.write')
    <form action="{{ route('fleet.vehicles.state', $vehicle->id) }}" method="POST" class="flex items-center gap-2">
        @csrf
        <label class="text-xs text-slate-500 dark:text-slate-400">{{ __('State') }}</label>
        <select name="state_id" onchange="this.form.submit()"
                class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
            @foreach ($states as $s)
                <option value="{{ $s->odoo_id }}" @selected($vehicle->odoo_state_id == $s->odoo_id)>{{ $s->name }}</option>
            @endforeach
        </select>
    </form>
    @endcan
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="space-y-5">
        {{-- Details --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('Details') }}</h2>
            <dl class="space-y-3 text-sm">
                @php $rows = [
                    [__('VIN'),              $vehicle->vin_sn],
                    [__('Model year'),       $vehicle->model_year],
                    [__('Fuel'),             $vehicle->fuel_type ? __(ucfirst(str_replace('_', ' ', $vehicle->fuel_type))) : null],
                    [__('Color'),            $vehicle->color],
                    [__('Seats'),            $vehicle->seats],
                    [__('Acquisition date'), $vehicle->acquisition_date?->format('Y-m-d')],
                    [__('Vehicle value'),    $vehicle->car_value ? number_format($vehicle->car_value, 0).' '.__('SAR') : null],
                ]; @endphp
                @foreach ($rows as [$label, $value])
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500 mb-0.5">{{ $label }}</dt>
                        <dd class="text-slate-900 dark:text-slate-100">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Driver --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('Driver') }}</h2>
            <p class="text-sm text-slate-900 dark:text-slate-100 mb-3">{{ $vehicle->driver_name ?: __('— None —') }}</p>
            @can('fleet.write')
            <form action="{{ route('fleet.vehicles.driver', $vehicle->id) }}" method="POST" class="space-y-2">
                @csrf
                <select name="employee_id"
                        class="w-full h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    <option value="">{{ __('— Unassign —') }}</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                    @endforeach
                </select>
                <button class="w-full h-9 rounded-md bg-slate-900 dark:bg-slate-700 text-white text-sm font-medium hover:bg-slate-800 transition">
                    {{ __('Set driver') }}
                </button>
            </form>
            @endcan
        </div>

        {{-- Odometer --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('Odometer') }}</h2>
            <p class="text-2xl font-bold tracking-tight text-brand-700 dark:text-brand-300 tabular-nums mb-3">
                {{ number_format($vehicle->odometer) }} <span class="text-xs text-slate-400 font-normal">{{ $vehicle->odometer_unit }}</span>
            </p>
            @can('fleet.write')
            <form action="{{ route('fleet.vehicles.odometer', $vehicle->id) }}" method="POST" class="flex gap-2">
                @csrf
                <input type="number" name="odometer" min="{{ (int) $vehicle->odometer }}" step="1" required dir="ltr"
                       placeholder="{{ number_format($vehicle->odometer) }}"
                       class="flex-1 h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                              focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:focus:ring-brand-900 focus:outline-none">
                <button class="h-9 px-4 rounded-md bg-slate-900 dark:bg-slate-700 text-white text-sm font-medium hover:bg-slate-800 transition">
                    {{ __('Update') }}
                </button>
            </form>
            @endcan
        </div>
    </div>

    {{-- Services --}}
    <div class="lg:col-span-2 space-y-5">
        @can('fleet.write')
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('Record a service') }}</h2>
            <form action="{{ route('fleet.vehicles.services.store', $vehicle->id) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @csrf
                <select name="service_type_id" required
                        class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    <option value="">{{ __('— Select —') }} ({{ __('Service type') }})</option>
                    @foreach ($serviceTypes as $t)
                        <option value="{{ $t->odoo_id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="description" placeholder="{{ __('Description') }}"
                       class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                <input type="number" name="amount" min="0" step="0.01" placeholder="{{ __('Cost (SAR)') }}" dir="ltr"
                       class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                <input type="date" name="date" value="{{ now()->toDateString() }}"
                       class="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                <button class="sm:col-span-2 h-9 rounded-md bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition">
                    {{ __('Record service') }}
                </button>
            </form>
        </div>
        @endcan

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('Service history') }}</h2>
            @forelse ($services as $s)
                <div class="flex items-center justify-between py-2.5 border-b last:border-0 border-slate-100 dark:border-slate-800">
                    <div>
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ $s->service_type_name ?: __('Service') }}{{ $s->description ? ' — '.$s->description : '' }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                            {{ $s->date?->format('Y-m-d') }}{{ $s->vendor_name ? ' · '.$s->vendor_name : '' }}
                        </p>
                    </div>
                    <div class="text-end">
                        @if ($s->amount)
                            <p class="text-sm font-bold text-slate-900 dark:text-slate-100 tabular-nums">{{ number_format($s->amount, 2) }}</p>
                        @endif
                        <span class="inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $s->stateColor() }}">
                            {{ $s->stateLabel() }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 dark:text-slate-500 text-center py-4">{{ __('No services recorded.') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
