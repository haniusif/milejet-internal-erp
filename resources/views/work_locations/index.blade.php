@extends('layouts.app')
@section('title', __('Offices'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400">{{ __('Branches & offices') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
            {{ __('Offices') }} <span class="text-slate-400 font-medium">({{ $locations->count() }})</span>
        </h1>
    </div>
    @can('work_locations.write')
    <a href="{{ route('work-locations.create') }}"
       class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition shadow-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        {{ __('New office') }}
    </a>
    @endcan
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-4 py-3 text-start font-medium">#</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Name') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Type') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Geofence') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Radius (m)') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ __('Employees') }}</th>
                <th class="px-4 py-3 text-end font-medium">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($locations as $l)
                <tr class="hover:bg-slate-50/60 transition {{ $l->active ? '' : 'opacity-50' }}">
                    <td class="px-4 py-3 text-xs text-slate-400 tabular-nums">{{ $l->odoo_id }}</td>
                    <td class="px-4 py-3 font-medium text-slate-900">
                        {{ $l->name }}
                        @unless($l->active)
                            <span class="ms-1 text-[10px] uppercase text-slate-400">({{ __('Archived') }})</span>
                        @endunless
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $l->location_type ? __(ucfirst($l->location_type)) : '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($l->hasGeofence())
                            <span class="inline-flex items-center gap-1 px-2 h-6 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700">
                                {{ __('Configured') }}
                            </span>
                            <span class="ms-1 text-xs text-slate-400 tabular-nums">{{ number_format($l->latitude, 5) }}, {{ number_format($l->longitude, 5) }}</span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 h-6 text-xs font-semibold rounded-full bg-amber-50 text-amber-700">
                                {{ __('Not set') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600 tabular-nums">
                        {{ $l->geofence_radius ?? __('Default (:radius m)', ['radius' => config('attendance.geofence_radius')]) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center justify-center min-w-[1.75rem] h-6 px-2 text-xs font-semibold rounded-full bg-slate-100 text-slate-700 tabular-nums">
                            {{ $l->employees_count }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-end whitespace-nowrap">
                        @can('work_locations.write')
                            <a href="{{ route('work-locations.edit', $l->id) }}"
                               class="inline-flex items-center text-xs text-brand-600 hover:text-brand-700 hover:underline">{{ __('Edit') }}</a>
                        @endcan
                        @can('work_locations.delete')
                            <form action="{{ route('work-locations.destroy', $l->id) }}" method="POST" class="inline ms-2"
                                  onsubmit="return confirm('{{ __('Delete office?') }}')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-rose-600 hover:text-rose-700 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        @endcan
                        @cannot('work_locations.write')
                            <span class="text-xs text-slate-400">{{ __('View only') }}</span>
                        @endcannot
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ __('No offices found.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
