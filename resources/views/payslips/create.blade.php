@extends('layouts.app')
@section('title', 'إنشاء قسيمة راتب')

@section('content')
<a href="{{ route('payslips.index') }}" class="text-sm text-indigo-600 hover:underline">← العودة</a>
<h1 class="text-2xl font-bold mt-1 mb-4">+ إنشاء قسيمة راتب</h1>

<form method="POST" action="{{ route('payslips.store') }}" class="bg-white rounded shadow p-6 max-w-xl space-y-4">
    @csrf

    <div>
        <label class="block text-sm font-medium mb-1">الموظف</label>
        <select name="employee_id" required
                class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            <option value="">— اختر موظف —</option>
            @foreach ($employees as $emp)
                <option value="{{ $emp->odoo_id }}" @selected(old('employee_id') == $emp->odoo_id)>
                    {{ $emp->name }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">يظهر فقط الموظفون الذين لديهم عقد سارٍ</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium mb-1">من تاريخ</label>
            <input type="date" name="date_from" value="{{ old('date_from', $default_from) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">إلى تاريخ</label>
            <input type="date" name="date_to" value="{{ old('date_to', $default_to) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="compute" value="0">
        <input type="checkbox" name="compute" value="1" checked class="rounded">
        احتساب البنود فوراً
    </label>

    <div class="flex gap-2 pt-2">
        <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded font-semibold">
            إنشاء
        </button>
        <a href="{{ route('payslips.index') }}"
           class="text-gray-600 px-5 py-2 hover:bg-gray-100 rounded">إلغاء</a>
    </div>
</form>
@endsection
