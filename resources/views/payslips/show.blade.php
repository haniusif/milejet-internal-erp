@extends('layouts.app')
@section('title', __('Payslip'))

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <a href="{{ route('payslips.index') }}" class="text-sm text-indigo-600 hover:underline">← {{ __('Back') }}</a>
        <h1 class="text-2xl font-bold mt-1">💰 {{ $payslip->number ?? __('Payslip #:id', ['id' => $payslip->id]) }}</h1>
    </div>
    <div class="flex gap-2">
        @can('payslips.create')
        <form method="POST" action="{{ route('payslips.compute', $payslip->id) }}">
            @csrf
            <button class="bg-blue-50 text-blue-700 px-3 py-1.5 rounded text-sm hover:bg-blue-100">
                🔄 {{ __('Recompute') }}
            </button>
        </form>
        @endcan
        @can('payslips.delete')
        <form method="POST" action="{{ route('payslips.destroy', $payslip->id) }}"
              onsubmit="return confirm('{{ __('Delete payslip?') }}')">
            @csrf @method('DELETE')
            <button class="bg-red-50 text-red-700 px-3 py-1.5 rounded text-sm hover:bg-red-100">
                {{ __('Delete') }}
            </button>
        </form>
        @endcan
    </div>
</div>

<div class="bg-white rounded shadow p-6 mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
            <p class="text-xs text-gray-500">{{ __('Employee') }}</p>
            <p class="font-semibold">{{ $payslip->employee_name }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">{{ __('Period') }}</p>
            <p class="font-semibold">{{ $payslip->date_from?->format('Y-m-d') }} → {{ $payslip->date_to?->format('Y-m-d') }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500">{{ __('Status') }}</p>
            <p>
                <span class="text-xs px-2 py-0.5 rounded {{ $payslip->stateColor() }}">
                    {{ $payslip->stateLabel() }}
                </span>
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500">{{ __('Last sync') }}</p>
            <p class="text-xs">{{ $payslip->synced_at?->diffForHumans() ?? '—' }}</p>
        </div>
    </div>
</div>

<div class="bg-white rounded shadow overflow-hidden mb-4">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-600 uppercase">
            <tr>
                <th class="px-3 py-2 text-start">{{ __('Code') }}</th>
                <th class="px-3 py-2 text-start">{{ __('Item') }}</th>
                <th class="px-3 py-2 text-start">{{ __('Category') }}</th>
                <th class="px-3 py-2 text-start">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($payslip->lines as $line)
            <tr class="border-b last:border-0
                @if($line->category_code === 'DED') bg-red-50/30
                @elseif($line->category_code === 'NET') bg-green-50/40 font-bold
                @elseif($line->category_code === 'GROSS') bg-blue-50/30 font-semibold
                @endif">
                <td class="px-3 py-2 text-xs text-gray-600 font-mono">{{ $line->code }}</td>
                <td class="px-3 py-2">{{ $line->name }}</td>
                <td class="px-3 py-2 text-xs text-gray-500">{{ $line->category_name ?? '—' }}</td>
                <td class="px-3 py-2 text-end
                    @if($line->category_code === 'DED') text-red-700
                    @elseif($line->category_code === 'NET') text-green-700 text-lg
                    @endif">
                    {{ number_format($line->total, 2) }} {{ __('SAR') }}
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-3 py-8 text-center text-gray-500">
                {{ __('Payslip not computed yet — press 🔄 Recompute') }}
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="bg-indigo-50 border border-indigo-200 rounded p-4 grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
    <div>
        <p class="text-xs text-gray-600">{{ __('Basic') }}</p>
        <p class="font-bold text-lg">{{ number_format($payslip->basic_total, 2) }}</p>
    </div>
    <div>
        <p class="text-xs text-gray-600">{{ __('Allowances') }}</p>
        <p class="font-bold text-lg">{{ number_format($payslip->allowance_total, 2) }}</p>
    </div>
    <div>
        <p class="text-xs text-gray-600">{{ __('Gross') }}</p>
        <p class="font-bold text-lg text-blue-700">{{ number_format($payslip->gross_total, 2) }}</p>
    </div>
    <div>
        <p class="text-xs text-gray-600">{{ __('Deductions') }}</p>
        <p class="font-bold text-lg text-red-700">{{ number_format($payslip->deduction_total, 2) }}</p>
    </div>
    <div>
        <p class="text-xs text-gray-600">{{ __('Net') }}</p>
        <p class="font-bold text-xl text-green-700">{{ number_format($payslip->net_total, 2) }}</p>
    </div>
</div>
@endsection
