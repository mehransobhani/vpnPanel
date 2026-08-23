@extends('layouts.app')
@section('title', 'نود VPN')

@php
    $field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none';
    $label = 'block text-xs text-slate-600 mb-1.5';
@endphp

@section('content')
    <h1 class="text-xl font-bold mb-1">نود VPN</h1>
    <p class="text-sm text-slate-500 mb-5">
        سرویس Xray روی همین سروری که پنل اجرا می‌شود. کانفیگ مشتری‌ها از اینجا ساخته می‌شود.
    </p>

    @if (! $node)
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center">
            <p class="text-slate-600 mb-4">هنوز نودی راه‌اندازی نشده است.</p>
            <p class="text-sm text-slate-500 mb-4">این دستور را در ترمینال سرور اجرا کنید:</p>
            <code class="block bg-slate-900 text-emerald-300 rounded-xl p-4 text-xs ltr text-left overflow-x-auto">
docker compose exec app php artisan panel:setup-local-node \<br>
&nbsp;&nbsp;&nbsp;&nbsp;--address=IP_عمومی_سرور --port=443<br><br>
docker compose --profile vpn up -d xray
            </code>
            <p class="text-xs text-slate-400 mt-4">
                کلید REALITY ساخته می‌شود، config.json نوشته می‌شود و نود همین‌جا ظاهر می‌شود.
            </p>
        </div>
    @else
        @php $online = $node->isOnline(); @endphp

        <div class="grid gap-3 grid-cols-2 sm:grid-cols-4 mb-5">
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-500 mb-1">وضعیت</p>
                <p class="font-bold flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full {{ $online ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                    {{ $node->is_active ? ($online ? 'آنلاین' : 'بی‌پاسخ') : 'غیرفعال' }}
                </p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-500 mb-1">سرویس فعال</p>
                <p class="font-bold">{{ number_format($subscriptionCount) }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-500 mb-1">اینباند</p>
                <p class="font-bold">{{ $node->inbounds->where('is_active', true)->count() }} فعال</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-500 mb-1">آخرین پاسخ</p>
                <p class="font-bold text-xs">{{ \App\Support\Format::jalali($node->last_seen_at, true) }}</p>
            </div>
        </div>

        <div class="flex gap-2 mb-5 flex-wrap">
            <form method="POST" action="{{ route('admin.node.test') }}">
                @csrf
                <button class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm hover:bg-slate-700">تست اتصال</button>
            </form>
            <form method="POST" action="{{ route('admin.node.resync') }}">
                @csrf
                <button class="px-4 py-2 rounded-lg border border-slate-300 bg-white text-sm hover:bg-slate-50">
                    همگام‌سازی مجدد کاربران
                </button>
            </form>
        </div>

        @if ($node->last_error)
            <div class="mb-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-xs text-rose-800 ltr font-mono">
                {{ $node->last_error }}
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-3">
            <form method="POST" action="{{ route('admin.node.update') }}"
                  class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4 h-fit">
                @csrf @method('PUT')
                <h2 class="font-bold">تنظیمات نود</h2>

                <div>
                    <label class="{{ $label }}">نام نمایشی *</label>
                    <input name="name" required value="{{ old('name', $node->name) }}" class="{{ $field }}">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="{{ $label }}">آدرس اتصال مشتری *</label>
                        <input name="address" required value="{{ old('address', $node->address) }}" class="{{ $field }} ltr">
                    </div>
                    <div>
                        <label class="{{ $label }}">کد کشور</label>
                        <input name="country" maxlength="2" value="{{ old('country', strtoupper((string) $node->country)) }}"
                               class="{{ $field }} ltr" placeholder="NL">
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 -mt-2">
                    IP یا دامنه‌ای که در کانفیگ مشتری می‌نشیند. باید از بیرون قابل دسترسی باشد.
                </p>

                <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
                    <legend class="text-xs text-slate-500 px-2">مسیرهای Xray</legend>
                    <div>
                        <label class="{{ $label }}">باینری</label>
                        <input name="xray_bin" required value="{{ old('xray_bin', $node->xray_bin) }}" class="{{ $field }} ltr">
                    </div>
                    <div>
                        <label class="{{ $label }}">آدرس API</label>
                        <input name="xray_api" required value="{{ old('xray_api', $node->xray_api) }}" class="{{ $field }} ltr">
                    </div>
                    <div>
                        <label class="{{ $label }}">مسیر config.json</label>
                        <input name="xray_config_path" required value="{{ old('xray_config_path', $node->xray_config_path) }}"
                               class="{{ $field }} ltr">
                    </div>
                </fieldset>

                <div>
                    <label class="{{ $label }}">یادداشت</label>
                    <textarea name="note" rows="2" class="{{ $field }}">{{ old('note', $node->note) }}</textarea>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="rounded" @checked(old('is_active', $node->is_active))>
                    نود فعال است
                </label>
                <p class="text-[11px] text-slate-400 -mt-2">
                    با غیرفعال کردن، لینک اشتراک همهٔ مشتری‌ها خالی می‌شود.
                </p>

                <button class="w-full py-2.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">ذخیره</button>
            </form>

            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-2xl border border-slate-200 p-4 text-xs text-slate-500">
                    هر اینباند اینجا باید <b>دقیقاً</b> با یک اینباند در
                    <code class="ltr">docker/xray/config.json</code> هم‌نام (<code class="ltr">tag</code>) باشد.
                    بعد از تغییر آن فایل:
                    <code class="ltr block mt-1 bg-slate-50 rounded p-2">docker compose --profile vpn restart xray</code>
                </div>

                @foreach ($node->inbounds->sortBy('sort') as $inbound)
                    @include('admin.inbound-form', ['inbound' => $inbound])
                @endforeach

                @include('admin.inbound-form', ['inbound' => null])
            </div>
        </div>
    @endif
@endsection
