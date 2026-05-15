@extends('layouts.app')
@section('title', 'تسجيل الدخول')

@section('content')
<div class="min-h-[80vh] flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-indigo-600">🏢 نظام HR</h1>
            <p class="text-gray-500 text-sm mt-1">سجّل دخولك ببيانات حسابك في Odoo</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">البريد الإلكتروني (Odoo)</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       placeholder="your.email@company.com"
                       class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">كلمة المرور أو API Key</label>
                <input type="password" name="password" required
                       class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <p class="text-xs text-gray-500 mt-1">
                    💡 يُفضّل استخدام API Key (من إعدادات الحساب في Odoo)
                </p>
            </div>

            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded font-semibold">
                تسجيل الدخول
            </button>
        </form>

        <div class="mt-6 text-xs text-gray-500 text-center">
            يتم التحقق من بياناتك مباشرة عبر Odoo
        </div>
    </div>
</div>
@endsection
