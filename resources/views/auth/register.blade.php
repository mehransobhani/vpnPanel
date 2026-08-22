@extends('layouts.guest')
@section('title', 'ثبت‌نام')

@section('content')
    <h2 class="text-lg font-bold mb-5">ساخت حساب جدید</h2>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1.5">نام</label>
            <input name="name" value="{{ old('name') }}" required autofocus
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
            <label class="block text-sm mb-1.5">ایمیل</label>
            <input name="email" type="email" value="{{ old('email') }}" required
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 ltr focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
            <label class="block text-sm mb-1.5">شمارهٔ تماس <span class="text-slate-400">(اختیاری)</span></label>
            <input name="phone" value="{{ old('phone') }}"
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 ltr focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
            <label class="block text-sm mb-1.5">رمز عبور</label>
            <input name="password" type="password" required
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 ltr focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
            <label class="block text-sm mb-1.5">تکرار رمز عبور</label>
            <input name="password_confirmation" type="password" required
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 ltr focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <button class="w-full py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">ثبت‌نام</button>
    </form>

    <p class="text-sm text-center text-slate-500 mt-5">
        قبلاً ثبت‌نام کرده‌اید؟ <a href="{{ route('login') }}" class="text-indigo-600 font-medium">ورود</a>
    </p>
@endsection
