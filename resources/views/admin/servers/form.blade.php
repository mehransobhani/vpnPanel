@extends('layouts.app')
@section('title', $server->exists ? 'ویرایش سرور' : 'سرور جدید')

@php
    $field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none';
    $label = 'block text-xs text-slate-600 mb-1.5';
@endphp

@section('content')
    <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <h1 class="text-xl font-bold">{{ $server->exists ? 'ویرایش: '.$server->name : 'افزودن سرور' }}</h1>

        @if ($server->exists)
            <div class="flex gap-2">
                <form method="POST" action="{{ route('admin.servers.test', $server) }}">
                    @csrf
                    <button class="px-3 py-1.5 rounded-lg border border-slate-300 text-sm hover:bg-white">تست اتصال</button>
                </form>
                <form method="POST" action="{{ route('admin.servers.resync', $server) }}">
                    @csrf
                    <button class="px-3 py-1.5 rounded-lg border border-slate-300 text-sm hover:bg-white">همگام‌سازی مجدد</button>
                </form>
                <form method="POST" action="{{ route('admin.servers.destroy', $server) }}"
                      onsubmit="return confirm('سرور و همهٔ اینباندهایش حذف شوند؟')">
                    @csrf @method('DELETE')
                    <button class="px-3 py-1.5 rounded-lg border border-rose-300 text-rose-600 text-sm hover:bg-rose-50">حذف</button>
                </form>
            </div>
        @endif
    </div>

    @if ($server->exists && $server->last_error)
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-xs text-rose-800 ltr font-mono">
            {{ $server->last_error }}
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- فرم سرور --}}
        <form method="POST" class="lg:col-span-1 bg-white rounded-2xl border border-slate-200 p-5 space-y-4 h-fit"
              action="{{ $server->exists ? route('admin.servers.update', $server) : route('admin.servers.store') }}">
            @csrf
            @if ($server->exists) @method('PUT') @endif

            <div>
                <label class="{{ $label }}">نام نمایشی *</label>
                <input name="name" required value="{{ old('name', $server->name) }}" class="{{ $field }}" placeholder="مثلاً: آلمان ۱">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="{{ $label }}">آدرس اتصال کاربر * <span class="text-slate-400">(دامنه یا IP)</span></label>
                    <input name="address" required value="{{ old('address', $server->address) }}" class="{{ $field }} ltr" placeholder="de1.example.com">
                </div>
                <div>
                    <label class="{{ $label }}">کد کشور</label>
                    <input name="country" maxlength="2" value="{{ old('country', $server->country) }}" class="{{ $field }} ltr" placeholder="DE">
                </div>
            </div>

            <div>
                <label class="{{ $label }}">روش مدیریت کاربران *</label>
                <select name="sync_driver" class="{{ $field }}">
                    <option value="local" @selected(old('sync_driver', $server->sync_driver) === 'local')>محلی — Xray روی همین سرور</option>
                    <option value="ssh" @selected(old('sync_driver', $server->sync_driver) === 'ssh')>SSH — سرور جدا، مدیریت خودکار</option>
                    <option value="manual" @selected(old('sync_driver', $server->sync_driver) === 'manual')>دستی — فقط ساخت کانفیگ</option>
                </select>
                <ul class="text-[11px] text-slate-400 mt-1 space-y-0.5">
                    <li><b>محلی:</b> نودی که با <code class="ltr">panel:setup-local-node</code> ساخته شده؛ نیازی به SSH ندارد.</li>
                    <li><b>SSH:</b> سرور خارجی جدا؛ اطلاعات SSH پایین را پر کنید.</li>
                    <li><b>دستی:</b> فقط لینک می‌سازد؛ افزودن کاربر روی نود با خودتان است.</li>
                </ul>
            </div>

            <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3 {{ $server->sync_driver === 'local' ? 'opacity-50' : '' }}">
                <legend class="text-xs text-slate-500 px-2">دسترسی SSH <span class="text-slate-400">(فقط برای درایور SSH)</span></legend>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="{{ $label }}">هاست <span class="text-slate-400">(خالی = آدرس بالا)</span></label>
                        <input name="ssh_host" value="{{ old('ssh_host', $server->ssh_host) }}" class="{{ $field }} ltr">
                    </div>
                    <div>
                        <label class="{{ $label }}">پورت</label>
                        <input name="ssh_port" type="number" required value="{{ old('ssh_port', $server->ssh_port ?? 22) }}" class="{{ $field }} ltr">
                    </div>
                </div>

                <div>
                    <label class="{{ $label }}">کاربر</label>
                    <input name="ssh_user" required value="{{ old('ssh_user', $server->ssh_user ?? 'root') }}" class="{{ $field }} ltr">
                </div>

                <div>
                    <label class="{{ $label }}">
                        رمز عبور
                        @if ($server->exists && $server->ssh_password)
                            <span class="text-emerald-600">(ذخیره شده — برای تغییر پر کنید)</span>
                        @endif
                    </label>
                    <input name="ssh_password" type="password" autocomplete="new-password" class="{{ $field }} ltr">
                </div>

                <div>
                    <label class="{{ $label }}">
                        یا کلید خصوصی SSH
                        @if ($server->exists && $server->ssh_private_key)
                            <span class="text-emerald-600">(ذخیره شده)</span>
                        @endif
                    </label>
                    <textarea name="ssh_private_key" rows="3" class="{{ $field }} ltr font-mono text-[11px]"
                              placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">اگر کلید رمز دارد، رمزش را در فیلد بالا بگذارید.</p>
                </div>
            </fieldset>

            <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
                <legend class="text-xs text-slate-500 px-2">مسیرهای Xray روی نود</legend>

                <div>
                    <label class="{{ $label }}">مسیر باینری</label>
                    <input name="xray_bin" required value="{{ old('xray_bin', $server->xray_bin ?? config('panel.xray.bin')) }}" class="{{ $field }} ltr">
                </div>
                <div>
                    <label class="{{ $label }}">آدرس API</label>
                    <input name="xray_api" required value="{{ old('xray_api', $server->xray_api ?? config('panel.xray.api')) }}" class="{{ $field }} ltr">
                </div>
                <div>
                    <label class="{{ $label }}">مسیر config.json</label>
                    <input name="xray_config_path" required class="{{ $field }} ltr"
                           value="{{ old('xray_config_path', $server->xray_config_path ?? '/usr/local/etc/xray/config.json') }}">
                </div>
            </fieldset>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $label }}">ظرفیت <span class="text-slate-400">(۰ = نامحدود)</span></label>
                    <input name="capacity" type="number" required min="0" value="{{ old('capacity', $server->capacity ?? 0) }}" class="{{ $field }} ltr">
                </div>
                <div>
                    <label class="{{ $label }}">ترتیب نمایش</label>
                    <input name="sort" type="number" required min="0" value="{{ old('sort', $server->sort ?? 0) }}" class="{{ $field }} ltr">
                </div>
            </div>

            <div>
                <label class="{{ $label }}">یادداشت</label>
                <textarea name="note" rows="2" class="{{ $field }}">{{ old('note', $server->note) }}</textarea>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" class="rounded" @checked(old('is_active', $server->is_active ?? true))>
                سرور فعال است
            </label>

            <button class="w-full py-2.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">ذخیره</button>
        </form>

        {{-- اینباندها --}}
        <div class="lg:col-span-2 space-y-4">
            @if (! $server->exists)
                <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500 text-sm">
                    ابتدا سرور را ذخیره کنید، سپس اینباندها را اضافه کنید.
                </div>
            @else
                @foreach ($server->inbounds->sortBy('sort') as $inbound)
                    @include('admin.servers.inbound-form', ['inbound' => $inbound, 'server' => $server])
                @endforeach

                @include('admin.servers.inbound-form', ['inbound' => null, 'server' => $server])
            @endif
        </div>
    </div>
@endsection
