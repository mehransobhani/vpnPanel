@extends('layouts.app')
@section('title', $user->name)

@php
    $field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none';
    $label = 'block text-xs text-slate-600 mb-1.5';
@endphp

@section('content')
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-bold">{{ $user->name }}</h1>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:underline">← فهرست کاربران</a>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.users.update', $user) }}"
              class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4 h-fit">
            @csrf @method('PUT')
            <h2 class="font-bold">اطلاعات کاربر</h2>

            <div>
                <label class="{{ $label }}">نام</label>
                <input name="name" required value="{{ old('name', $user->name) }}" class="{{ $field }}">
            </div>
            <div>
                <label class="{{ $label }}">ایمیل</label>
                <input name="email" type="email" required value="{{ old('email', $user->email) }}" class="{{ $field }} ltr">
            </div>
            <div>
                <label class="{{ $label }}">شمارهٔ تماس</label>
                <input name="phone" value="{{ old('phone', $user->phone) }}" class="{{ $field }} ltr">
            </div>
            <div>
                <label class="{{ $label }}">شناسهٔ تلگرام</label>
                <input name="telegram_id" value="{{ old('telegram_id', $user->telegram_id) }}" class="{{ $field }} ltr">
            </div>
            <div>
                <label class="{{ $label }}">موجودی کیف پول ({{ config('panel.currency') }})</label>
                <input name="balance" type="number" min="0" required value="{{ old('balance', $user->balance) }}" class="{{ $field }} ltr">
            </div>
            <div>
                <label class="{{ $label }}">رمز عبور جدید <span class="text-slate-400">(خالی = بدون تغییر)</span></label>
                <input name="password" type="password" autocomplete="new-password" class="{{ $field }} ltr">
            </div>
            <div>
                <label class="{{ $label }}">تکرار رمز جدید</label>
                <input name="password_confirmation" type="password" class="{{ $field }} ltr">
            </div>

            @if ($user->id !== auth()->id())
                <div class="flex gap-4 text-sm pt-2 border-t border-slate-100">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_admin" value="1" class="rounded" @checked($user->is_admin)> مدیر
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" class="rounded" @checked($user->is_active)> حساب فعال
                    </label>
                </div>
            @else
                <p class="text-xs text-slate-400 pt-2 border-t border-slate-100">
                    نمی‌توانید دسترسی مدیریتی خودتان را تغییر دهید.
                </p>
            @endif

            <button class="w-full py-2.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">ذخیره</button>
        </form>

        <div class="lg:col-span-2 space-y-5">
            {{-- ساخت سرویس دستی --}}
            <form method="POST" action="{{ route('admin.subscriptions.store') }}"
                  class="bg-white rounded-2xl border border-slate-200 p-5">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <h2 class="font-bold mb-1">ساخت سرویس دستی</h2>
                <p class="text-xs text-slate-500 mb-3">بدون ثبت سفارش و پرداخت — مثلاً برای تست یا هدیه.</p>

                <div class="flex gap-2">
                    <select name="plan_id" required class="{{ $field }} flex-1">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} — {{ $plan->duration_days }} روز / {{ $plan->traffic_gb ?: '∞' }}GB</option>
                        @endforeach
                    </select>
                    <button class="px-5 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-500 shrink-0">بساز</button>
                </div>
            </form>

            <section class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-bold mb-3">سرویس‌ها ({{ $user->subscriptions->count() }})</h2>
                @forelse ($user->subscriptions as $sub)
                    <a href="{{ route('admin.subscriptions.show', $sub) }}"
                       class="flex items-center justify-between py-2.5 border-b border-slate-100 last:border-0 hover:bg-slate-50 -mx-2 px-2 rounded">
                        <span class="text-sm">
                            {{ $sub->remark }}
                            <span class="text-xs text-slate-400">#{{ $sub->id }}</span>
                        </span>
                        <span class="text-xs text-slate-500 ltr">
                            {{ \App\Support\Format::bytes($sub->used_traffic) }} — {{ \App\Support\Format::jalali($sub->expires_at) }}
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500 py-4 text-center">سرویسی ندارد.</p>
                @endforelse
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-bold mb-3">سفارش‌ها ({{ $user->orders->count() }})</h2>
                @forelse ($user->orders as $order)
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100 last:border-0 text-sm">
                        <span class="ltr text-xs font-mono">{{ $order->code }}</span>
                        <span>{{ $order->plan->name }}</span>
                        <span>{{ \App\Support\Format::money($order->amount) }}</span>
                        @include('partials.order-status', ['status' => $order->status])
                    </div>
                @empty
                    <p class="text-sm text-slate-500 py-4 text-center">سفارشی ندارد.</p>
                @endforelse
            </section>
        </div>
    </div>
@endsection
