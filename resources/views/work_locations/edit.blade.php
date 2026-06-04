@extends('layouts.app')
@section('title', __('Edit office'))
@section('content')
    <h1 class="text-2xl font-bold mb-4">{{ __('Edit office') }}: {{ $location->name }}</h1>
    <form method="POST" action="{{ route('work-locations.update', $location->id) }}" class="bg-white rounded shadow p-6 space-y-4 max-w-2xl">
        @csrf @method('PUT')
        @include('work_locations._form')
        <div class="flex gap-2 pt-4 border-t">
            <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded">{{ __('Save') }}</button>
            <a href="{{ route('work-locations.index') }}" class="px-6 py-2 rounded border">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
