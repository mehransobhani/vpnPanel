@extends('layouts.guest')
@section('title', 'ورود')

@section('content')
    <h2 class="text-lg font-bold mb-5">ورود به حساب</h2>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1.5">ایمیل</label>
            <input name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 ltr focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
            <label class="block text-sm mb-1.5">رمز عبور</label>
            <input name="password" type="password" required
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 ltr focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded"> مرا به خاطر بسپار
        </label>
        <button class="w-full py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium">ورود</button>
    </form>

    <p class="text-sm text-center text-slate-500 mt-5">
        حساب ندارید؟ <a href="{{ route('register') }}" class="text-indigo-600 font-medium">ثبت‌نام</a>
    </p>
@endsection
