@extends('layouts.app')
@section('title', __('Login'))

@section('content')
<div class="min-h-[80vh] flex items-center justify-center">
    <div class="bg-white dark:bg-slate-900 p-8 rounded-lg shadow-md w-full max-w-md ring-1 ring-slate-200 dark:ring-slate-800">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">🏢 {{ __('HR System') }}</h1>
            <p class="text-gray-500 dark:text-slate-400 text-sm mt-1">{{ __('Sign in to your account using your Odoo credentials') }}</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">{{ __('Email (Odoo)') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       placeholder="your.email@company.com"
                       class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none
                              bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700
                              text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">{{ __('Password or API Key') }}</label>
                <input type="password" name="password" required
                       class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none
                              bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700
                              text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500">
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                    {{ __('Tip: prefer using an API Key from your Odoo account settings.') }}
                </p>
            </div>

            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded font-semibold">
                {{ __('Sign in') }}
            </button>
        </form>

        <div class="mt-6 text-xs text-gray-500 dark:text-slate-400 text-center">
            {{ __('Your credentials are verified directly with Odoo.') }}
        </div>
    </div>
</div>
@endsection
