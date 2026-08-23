@extends('layouts.app')
@section('title', $plan->exists ? 'ویرایش پلن' : 'پلن جدید')

@php
    $field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none';
    $label = 'block text-xs text-slate-600 mb-1.5';
@endphp

@section('content')
    <div class="max-w-2xl">
        <h1 class="text-xl font-bold mb-5">{{ $plan->exists ? 'ویرایش: '.$plan->name : 'ساخت پلن جدید' }}</h1>

        <form method="POST" class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4"
              action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}">
            @csrf
            @if ($plan->exists) @method('PUT') @endif

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">نام پلن *</label>
                    <input name="name" required value="{{ old('name', $plan->name) }}" class="{{ $field }}" placeholder="یک‌ماهه ۵۰ گیگ">
                </div>
                <div>
                    <label class="{{ $label }}">Slug <span class="text-slate-400">(خالی = خودکار)</span></label>
                    <input name="slug" value="{{ old('slug', $plan->slug) }}" class="{{ $field }} ltr">
                </div>
            </div>

            <div>
                <label class="{{ $label }}">توضیح کوتاه</label>
                <textarea name="description" rows="2" class="{{ $field }}">{{ old('description', $plan->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <label class="{{ $label }}">مدت (روز) *</label>
                    <input name="duration_days" type="number" required min="1" class="{{ $field }} ltr"
                           value="{{ old('duration_days', $plan->duration_days ?? 30) }}">
                </div>
                <div>
                    <label class="{{ $label }}">حجم (GB) *</label>
                    <input name="traffic_gb" type="number" required min="0" class="{{ $field }} ltr"
                           value="{{ old('traffic_gb', $plan->traffic_gb ?? 50) }}">
                    <p class="text-[11px] text-slate-400 mt-1">۰ = نامحدود</p>
                </div>
                <div>
                    <label class="{{ $label }}">دستگاه هم‌زمان *</label>
                    <input name="device_limit" type="number" required min="1" class="{{ $field }} ltr"
                           value="{{ old('device_limit', $plan->device_limit ?? 2) }}">
                </div>
                <div>
                    <label class="{{ $label }}">ترتیب</label>
                    <input name="sort" type="number" required min="0" class="{{ $field }} ltr" value="{{ old('sort', $plan->sort ?? 0) }}">
                </div>
            </div>

            <div>
                <label class="{{ $label }}">قیمت ({{ config('panel.currency') }}) *</label>
                <input name="price" type="number" required min="0" class="{{ $field }} ltr" value="{{ old('price', $plan->price ?? 0) }}">
            </div>

            <div class="flex gap-5 text-sm pt-2 border-t border-slate-100">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="rounded" @checked(old('is_active', $plan->is_active ?? true))>
                    فعال (قابل خرید)
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_featured" value="1" class="rounded" @checked(old('is_featured', $plan->is_featured ?? false))>
                    نمایش به‌عنوان پیشنهاد ویژه
                </label>
            </div>

            <div class="flex gap-2">
                <button class="px-6 py-2.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">ذخیره</button>
                <a href="{{ route('admin.plans.index') }}" class="px-6 py-2.5 rounded-lg border border-slate-300 text-sm hover:bg-slate-50">انصراف</a>
            </div>
        </form>

        @if ($plan->exists)
            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="mt-4"
                  onsubmit="return confirm('این پلن حذف شود؟ سرویس‌های موجود دست‌نخورده می‌مانند.')">
                @csrf @method('DELETE')
                <button class="text-sm text-rose-600 hover:underline">حذف پلن</button>
            </form>
        @endif
    </div>
@endsection
