@extends('layouts.app')
@section('title', __('Create payslip'))

@section('content')
<a href="{{ route('payslips.index') }}" class="text-sm text-indigo-600 hover:underline">← {{ __('Back') }}</a>
<h1 class="text-2xl font-bold mt-1 mb-4">+ {{ __('Create payslip') }}</h1>

<form method="POST" action="{{ route('payslips.store') }}" class="bg-white rounded shadow p-6 max-w-xl space-y-4">
    @csrf

    <div>
        <label class="block text-sm font-medium mb-1">{{ __('Employee') }}</label>
        <select name="employee_id" required
                class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            <option value="">{{ __('— Select an employee —') }}</option>
            @foreach ($employees as $emp)
                <option value="{{ $emp->odoo_id }}" @selected(old('employee_id') == $emp->odoo_id)>
                    {{ $emp->name }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">{{ __('Only employees with an active contract are shown') }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium mb-1">{{ __('From date') }}</label>
            <input type="date" name="date_from" value="{{ old('date_from', $default_from) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">{{ __('To date') }}</label>
            <input type="date" name="date_to" value="{{ old('date_to', $default_to) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="compute" value="0">
        <input type="checkbox" name="compute" value="1" checked class="rounded">
        {{ __('Compute lines immediately') }}
    </label>

    <div class="flex gap-2 pt-2">
        <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded font-semibold">
            {{ __('Create') }}
        </button>
        <a href="{{ route('payslips.index') }}"
           class="text-gray-600 px-5 py-2 hover:bg-gray-100 rounded">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
