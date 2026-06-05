@extends('layouts.app')
@section('title', __('Customers'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('CRM') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">
            {{ __('Customers') }} <span class="text-slate-400 font-medium">({{ $customers->total() }})</span>
        </h1>
    </div>
    @can('crm.write')
    <a href="{{ route('crm.customers.create') }}"
       class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition shadow-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        {{ __('New customer') }}
    </a>
    @endcan
</div>

@include('crm._subnav')

<form method="GET" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
    <div class="relative flex-1 min-w-[180px]">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             class="ico-sm absolute {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }} top-1/2 -translate-y-1/2 text-slate-400">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search customers...') }}"
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
                    <th class="text-start font-medium px-4 py-3">{{ __('Customer') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Contact') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('City') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('VAT') }}</th>
                    <th class="text-start font-medium px-4 py-3">{{ __('Opportunities') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($customers as $c)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="grid place-items-center w-8 h-8 rounded-md {{ $c->is_company ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                                    @if ($c->is_company)
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                            <path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/>
                                        </svg>
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                        </svg>
                                    @endif
                                </span>
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $c->name }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                            <p dir="ltr">{{ $c->email ?: '—' }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500" dir="ltr">{{ $c->phone ?: $c->mobile }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            {{ $c->city ?: '—' }}{{ $c->city && $c->country_name ? ', ' : '' }}{{ !$c->city ? ($c->country_name ?: '') : $c->country_name }}
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 font-mono text-xs">{{ $c->vat ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('crm.index', ['q' => $c->name]) }}" class="text-brand-600 dark:text-brand-400 hover:underline tabular-nums">
                                {{ $c->leads_count }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-12 text-sm text-slate-400 dark:text-slate-500">{{ __('No customers found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $customers->links() }}</div>
@endsection
