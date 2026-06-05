@extends('layouts.app')
@section('title', __('Services'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Fleet') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">
            {{ __('Services') }} <span class="text-slate-400 font-medium">({{ $services->total() }})</span>
        </h1>
    </div>
</div>


<form method="GET" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
    <div class="relative flex-1 min-w-[180px]">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             class="ico-sm absolute {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 text-slate-400">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search services...') }}"
               class="w-full h-9 {{ app()->getLocale() === 'ar' ? 'pe-9 ps-3' : 'ps-9 pe-3' }} border border-slate-200 dark:border-slate-700 rounded-md text-sm
                      bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                      focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:focus:ring-brand-900 focus:outline-none">
    </div>
    <button class="h-9 px-4 rounded-md bg-slate-900 dark:bg-slate-700 text-white text-sm">{{ __('Search') }}</button>
</form>

<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                    <th class="text-start font-medium px-4 py-3">{{ __('Vehicle') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Service') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Date') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Cost (SAR)') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($services as $s)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition {{ $s->vehicle ? 'cursor-pointer' : '' }}"
                        @if ($s->vehicle) onclick="window.location='{{ route('fleet.vehicles.show', $s->vehicle->id) }}'" @endif>
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $s->vehicle_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                            {{ $s->service_type_name ?: __('Service') }}{{ $s->description ? ' — '.$s->description : '' }}
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 tabular-nums text-xs">{{ $s->date?->format('Y-m-d') ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300 tabular-nums">{{ $s->amount ? number_format($s->amount, 2) : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full ring-1 {{ $s->stateColor() }}">
                                {{ $s->stateLabel() }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-12 text-sm text-slate-400 dark:text-slate-500">{{ __('No services recorded.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $services->links() }}</div>
@endsection
