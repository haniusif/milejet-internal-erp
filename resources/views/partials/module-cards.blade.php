{{--
    Module launcher cards — the app switcher used on the portal hub (/) and
    on the HR dashboard. Self-contained: pulls links from config and gates
    each card against the current user. Locked modules render disabled.
--}}
@php
    $links = config('domains.links');
    $apps = [
        [
            'label' => __('HR'),
            'desc'  => __('Employees, leaves, attendance, contracts and payroll'),
            'href'  => $links['hr'] ?: route('dashboard'),
            'external' => (bool) $links['hr'],
            'grad'  => 'from-brand-500 to-brand-700',
            'icon'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        ],
        [
            'label' => __('CRM'),
            'desc'  => __('Customers, leads and sales pipeline'),
            'href'  => $links['crm'] ?: route('crm.index'),
            'external' => (bool) $links['crm'],
            'gate'  => 'crm.view',
            'grad'  => 'from-sky-500 to-sky-700',
            'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>',
        ],
        [
            'label' => __('Fleet'),
            'desc'  => __('Vehicles, drivers and trips'),
            'href'  => $links['fleet'],
            'external' => true,
            'gate'  => 'fleet.view',
            'grad'  => 'from-emerald-500 to-emerald-700',
            'icon'  => '<path d="M5 17h-2v-6l2-5h9l4 5h3a2 2 0 0 1 2 2v4h-2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/>',
        ],
        [
            'label' => __('Finance'),
            'desc'  => __('Accounting, invoices and financial reports'),
            'href'  => $links['finance'],
            'external' => true,
            'gate'  => 'finance.view',
            'grad'  => 'from-amber-500 to-amber-700',
            'icon'  => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        ],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    @foreach ($apps as $app)
        {{-- Role-based: only render modules this user's roles grant access to --}}
        @continue(isset($app['gate']) && !auth()->user()->can($app['gate']))
        <a href="{{ $app['href'] }}" @if($app['external']) rel="noopener" @endif
           class="group flex items-start gap-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 transition
                  hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-soft">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-gradient-to-br {{ $app['grad'] }} text-white shadow-sm shrink-0">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                    {!! $app['icon'] !!}
                </svg>
            </span>
            <span class="flex-1 min-w-0">
                <span class="flex items-center gap-1.5 text-base font-semibold text-slate-900 dark:text-slate-100">
                    {{ $app['label'] }}
                    @if ($app['external'])
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round"
                             class="w-3.5 h-3.5 text-slate-300 dark:text-slate-600 group-hover:text-slate-500 dark:group-hover:text-slate-400 transition">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/>
                        </svg>
                    @endif
                </span>
                <span class="block text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $app['desc'] }}</span>
            </span>
        </a>
    @endforeach
</div>
