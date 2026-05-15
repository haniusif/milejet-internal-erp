@extends('layouts.app')
@section('title', __('Leaves'))

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">{{ __('Leaves') }} ({{ $leaves->total() }})</h1>
        <a href="{{ route('leaves.create') }}"
           class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded text-sm">+ {{ __('New request') }}</a>
    </div>

    <div class="bg-white p-3 rounded shadow mb-4 flex flex-wrap gap-2 text-sm">
        @php
            $states = [
                ''         => __('All'),
                'draft'    => __('Leave state: draft'),
                'confirm'  => __('Leave state: confirm'),
                'validate' => __('Leave state: validate'),
                'refuse'   => __('Leave state: refuse'),
                'cancel'   => __('Leave state: cancel'),
            ];
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
            <thead class="bg-gray-100 text-start">
                <tr>
                    <th class="px-3 py-2 text-start">#</th>
                    <th class="px-3 py-2 text-start">{{ __('Employee') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('Type') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('From') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('To') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('Days') }}</th>
                    <th class="px-3 py-2 text-start">{{ __('Status') }}</th>
                    <th class="px-3 py-2 text-end">{{ __('Actions') }}</th>
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
                        <td class="px-3 py-2 text-end whitespace-nowrap">
                            @if (in_array($l->state, ['draft', 'confirm']))
                                <form action="{{ route('leaves.approve', $l->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="text-green-600 hover:underline">{{ __('Approve') }}</button>
                                </form>
                                <form action="{{ route('leaves.refuse', $l->id) }}" method="POST" class="inline ms-2">
                                    @csrf
                                    <button class="text-orange-600 hover:underline">{{ __('Refuse') }}</button>
                                </form>
                            @endif
                            <form action="{{ route('leaves.destroy', $l->id) }}" method="POST"
                                  class="inline ms-2" onsubmit="return confirm('{{ __('Delete?') }}')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">{{ __('No requests found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $leaves->links() }}</div>
@endsection
