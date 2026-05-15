@extends('layouts.app')
@section('title', __('Edit department'))
@section('content')
    <h1 class="text-2xl font-bold mb-4">{{ __('Edit: :name', ['name' => $department->name]) }}</h1>
    <form method="POST" action="{{ route('departments.update', $department->id) }}" class="bg-white rounded shadow p-6 space-y-4 max-w-2xl">
        @csrf @method('PUT')
        @include('departments._form', ['department' => $department])
        <div class="flex gap-2 pt-4 border-t">
            <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded">{{ __('Save changes') }}</button>
            <a href="{{ route('departments.index') }}" class="px-6 py-2 rounded border">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
