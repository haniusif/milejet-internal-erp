@extends('layouts.app')
@section('title', __('Org Chart'))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Human Resources') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">{{ __('Org Chart') }}</h1>
    </div>
    <a href="{{ route('employees.index') }}"
       class="inline-flex items-center gap-2 h-9 px-4 rounded-md bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200
              dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ico-sm">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        {{ __('Employees') }}
    </a>
</div>

<style>
    .org-tree { --line: #cbd5e1; }
    .dark .org-tree { --line: #334155; }

    .org-tree ul {
        display: flex;
        justify-content: center;
        padding-top: 1.25rem;
        position: relative;
    }
    .org-tree li {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        padding: 1.25rem 0.375rem 0;
    }
    /* horizontal + vertical connectors */
    .org-tree li::before, .org-tree li::after {
        content: ""; position: absolute; top: 0; right: 50%;
        border-top: 1px solid var(--line);
        width: 50%; height: 1.25rem;
    }
    .org-tree li::after {
        right: auto; left: 50%;
        border-left: 1px solid var(--line);
    }
    .org-tree li:only-child::before, .org-tree li:only-child::after { display: none; }
    .org-tree li:only-child { padding-top: 0; }
    .org-tree li:first-child::before, .org-tree li:last-child::after { border: 0 none; }
    .org-tree li:last-child::before {
        border-right: 1px solid var(--line);
        border-radius: 0 0.375rem 0 0;
    }
    .org-tree li:first-child::after { border-radius: 0.375rem 0 0 0; }
    .org-tree ul ul::before {
        content: ""; position: absolute; top: 0; left: 50%;
        border-left: 1px solid var(--line);
        width: 0; height: 1.25rem;
    }
    /* top-level list draws no connectors above the root(s) */
    .org-tree > ul { padding-top: 0; }
    .org-tree > ul > li { padding-top: 0; }
    .org-tree > ul > li::before, .org-tree > ul > li::after { display: none; }
</style>

{{-- dir=ltr keeps the connector geometry stable; card text stays RTL-aware --}}
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 overflow-x-auto">
    <div class="org-tree inline-block min-w-full" dir="ltr">
        <ul>
            @foreach ($roots as $root)
                @include('employees._org_node', ['employee' => $root])
            @endforeach
        </ul>
    </div>
</div>
@endsection
