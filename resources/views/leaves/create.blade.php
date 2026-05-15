@extends('layouts.app')
@section('title', __('New leave request'))

@section('content')
    <h1 class="text-2xl font-bold mb-4">{{ __('New leave request') }}</h1>

    <form method="POST" action="{{ route('leaves.store') }}" class="bg-white rounded shadow p-6 space-y-4 max-w-2xl">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Employee') }} *</label>
            <select name="employee_id" required class="w-full border rounded px-3 py-2 bg-white">
                <option value="">{{ __('— Select —') }}</option>
                @foreach ($employees as $e)
                    <option value="{{ $e->odoo_id }}" {{ (int) old('employee_id') === $e->odoo_id ? 'selected' : '' }}>
                        {{ $e->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Leave type') }} *</label>
            <select name="holiday_status_id" required class="w-full border rounded px-3 py-2 bg-white">
                <option value="">{{ __('— Select —') }}</option>
                @foreach ($leaveTypes as $t)
                    <option value="{{ $t->odoo_id }}" {{ (int) old('holiday_status_id') === $t->odoo_id ? 'selected' : '' }}>
                        {{ $t->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('From') }} *</label>
                <input type="date" name="date_from" required value="{{ old('date_from') }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('To') }} *</label>
                <input type="date" name="date_to" required value="{{ old('date_to') }}" class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __('Reason') }}</label>
            <textarea name="name" rows="3" class="w-full border rounded px-3 py-2">{{ old('name') }}</textarea>
        </div>

        <div class="flex gap-2 pt-4 border-t">
            <button class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded">{{ __('Submit') }}</button>
            <a href="{{ route('leaves.index') }}" class="px-6 py-2 rounded border">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
