@extends('layouts.app')
@section('title', __('New department'))
@section('content')
    <h1 class="text-2xl font-bold mb-4">{{ __('Add new department') }}</h1>
    <form method="POST" action="{{ route('departments.store') }}" class="bg-white rounded shadow p-6 space-y-4 max-w-2xl">
        @csrf
        @include('departments._form', ['department' => null])
        <div class="flex gap-2 pt-4 border-t">
            <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded">{{ __('Save') }}</button>
            <a href="{{ route('departments.index') }}" class="px-6 py-2 rounded border">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
