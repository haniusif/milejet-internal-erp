@extends('layouts.app')
@section('title', __('New employee'))
@section('content')
    <h1 class="text-2xl font-bold mb-4">{{ __('Add new employee') }}</h1>
    <form method="POST" action="{{ route('employees.store') }}" class="bg-white rounded shadow p-6 space-y-4">
        @csrf
        @include('employees._form', ['employee' => null])
        <div class="flex gap-2 pt-4 border-t">
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded">{{ __('Save') }}</button>
            <a href="{{ route('employees.index') }}" class="px-6 py-2 rounded border">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
