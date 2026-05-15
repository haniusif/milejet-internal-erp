@extends('layouts.app')
@section('title', 'الإجازات')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">الإجازات ({{ $leaves->total() }})</h1>
        <a href="{{ route('leaves.create') }}"
           class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded text-sm">+ طلب جديد</a>
    </div>

    <div class="bg-white p-3 rounded shadow mb-4 flex flex-wrap gap-2 text-sm">
        @php
            $states = ['' => 'الكل', 'draft' => 'مسودة', 'confirm' => 'قيد الموافقة',
                       'validate' => 'موافق عليها', 'refuse' => 'مرفوضة', 'cancel' => 'ملغاة'];
            $current = request('state', '');
        @endphp
        @foreach ($states as $key => $label)
            <a href="{{ route('leaves.index', $key ? ['state' => $key] : []) }}"
               class="px-3 py-1 rounded {{ $current === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-right">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">الموظف</th>
                    <th class="px-3 py-2">النوع</th>
                    <th class="px-3 py-2">من</th>
                    <th class="px-3 py-2">إلى</th>
                    <th class="px-3 py-2">الأيام</th>
                    <th class="px-3 py-2">الحالة</th>
                    <th class="px-3 py-2 text-left">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leaves as $l)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-3 py-2 text-gray-400">{{ $l->odoo_id }}</td>
                        <td class="px-3 py-2 font-medium">{{ $l->employee_name }}</td>
                        <td class="px-3 py-2">{{ $l->leave_type_name ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $l->date_from?->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">{{ $l->date_to?->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">{{ $l->number_of_days }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded text-xs {{ $l->stateColor() }}">
                                {{ $l->stateLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-left whitespace-nowrap">
                            @if (in_array($l->state, ['draft', 'confirm']))
                                <form action="{{ route('leaves.approve', $l->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="text-green-600 hover:underline">موافقة</button>
                                </form>
                                <form action="{{ route('leaves.refuse', $l->id) }}" method="POST" class="inline mr-2">
                                    @csrf
                                    <button class="text-orange-600 hover:underline">رفض</button>
                                </form>
                            @endif
                            <form action="{{ route('leaves.destroy', $l->id) }}" method="POST"
                                  class="inline mr-2" onsubmit="return confirm('حذف؟')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">لا توجد طلبات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $leaves->links() }}</div>
@endsection
