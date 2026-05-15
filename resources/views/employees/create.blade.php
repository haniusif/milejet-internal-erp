@extends('layouts.app')
@section('title', 'موظف جديد')
@section('content')
    <h1 class="text-2xl font-bold mb-4">إضافة موظف جديد</h1>
    <form method="POST" action="{{ route('employees.store') }}" class="bg-white rounded shadow p-6 space-y-4">
        @csrf
        @include('employees._form', ['employee' => null])
        <div class="flex gap-2 pt-4 border-t">
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded">حفظ</button>
            <a href="{{ route('employees.index') }}" class="px-6 py-2 rounded border">إلغاء</a>
        </div>
    </form>
@endsection
