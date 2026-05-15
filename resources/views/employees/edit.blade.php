@extends('layouts.app')
@section('title', 'تعديل موظف')
@section('content')
    <h1 class="text-2xl font-bold mb-4">تعديل: {{ $employee->name }}</h1>
    <form method="POST" action="{{ route('employees.update', $employee->id) }}" class="bg-white rounded shadow p-6 space-y-4">
        @csrf @method('PUT')
        @include('employees._form', ['employee' => $employee])
        <div class="flex gap-2 pt-4 border-t">
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded">حفظ التعديلات</button>
            <a href="{{ route('employees.index') }}" class="px-6 py-2 rounded border">إلغاء</a>
        </div>
    </form>
@endsection
