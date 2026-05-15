@extends('layouts.app')
@section('title', 'الحضور والانصراف')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">الحضور والانصراف</h1>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">حضور اليوم</p>
            <p class="text-2xl font-bold text-indigo-600">{{ $todayStats['present'] }}</p>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <p class="text-xs text-gray-500">حالياً في العمل</p>
            <p class="text-2xl font-bold text-green-600">{{ $todayStats['checked_in'] }}</p>
        </div>
    </div>

    <!-- نموذج تسجيل حضور -->
    <div class="bg-white p-4 rounded shadow mb-4">
        <h2 class="font-semibold mb-3">تسجيل حضور جديد</h2>
        <form method="POST" action="{{ route('attendances.check-in') }}" class="flex gap-2">
            @csrf
            <select name="employee_id" required class="flex-1 border rounded px-3 py-2 bg-white text-sm">
                <option value="">— اختر موظف —</option>
                @foreach ($employees as $e)
                    <option value="{{ $e->odoo_id }}">{{ $e->name }}</option>
                @endforeach
            </select>
            <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm">
                ⏰ تسجيل حضور
            </button>
        </form>
    </div>

    <!-- فلتر -->
    <form method="GET" class="bg-white p-3 rounded shadow mb-4 flex gap-2 flex-wrap text-sm">
        <select name="employee_id" class="border rounded px-3 py-2 bg-white">
            <option value="">كل الموظفين</option>
            @foreach ($employees as $e)
                <option value="{{ $e->odoo_id }}" {{ request('employee_id') == $e->odoo_id ? 'selected' : '' }}>
                    {{ $e->name }}
                </option>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ request('date') }}" class="border rounded px-3 py-2">
        <button class="bg-gray-800 text-white px-4 py-2 rounded">فلتر</button>
        @if (request('employee_id') || request('date'))
            <a href="{{ route('attendances.index') }}" class="px-4 py-2 rounded bg-gray-200">مسح</a>
        @endif
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-right">
                <tr>
                    <th class="px-3 py-2">الموظف</th>
                    <th class="px-3 py-2">الحضور</th>
                    <th class="px-3 py-2">الانصراف</th>
                    <th class="px-3 py-2">ساعات العمل</th>
                    <th class="px-3 py-2 text-left">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attendances as $a)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium">{{ $a->employee_name }}</td>
                        <td class="px-3 py-2 text-xs">{{ $a->check_in?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2 text-xs">
                            @if ($a->check_out)
                                {{ $a->check_out->format('Y-m-d H:i') }}
                            @else
                                <span class="text-green-600 font-semibold">⏰ في العمل</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $a->worked_hours }} س</td>
                        <td class="px-3 py-2 text-left whitespace-nowrap">
                            @if (!$a->check_out)
                                <form action="{{ route('attendances.check-out', $a->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="text-orange-600 hover:underline">تسجيل انصراف</button>
                                </form>
                            @endif
                            <form action="{{ route('attendances.destroy', $a->id) }}" method="POST"
                                  class="inline mr-2" onsubmit="return confirm('حذف السجل؟')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">لا توجد سجلات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $attendances->links() }}</div>
@endsection
