@extends('layouts.app')
@section('title', $subscription->remark)

@section('content')
    <div class="flex items-center justify-between mb-5 gap-3">
        <div>
            <h1 class="text-xl font-bold">{{ $subscription->remark }}</h1>
            <p class="text-sm text-slate-500">{{ $subscription->plan?->name }}</p>
        </div>
        <a href="{{ route('subscriptions.index') }}" class="text-sm text-slate-500 hover:underline">← همهٔ سرویس‌ها</a>
    </div>

    @unless ($subscription->isUsable())
        <div class="mb-5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
            این سرویس در حال حاضر <b>فعال نیست</b>
            ({{ $subscription->status === 'expired' ? 'منقضی شده' : ($subscription->status === 'exhausted' ? 'حجم تمام شده' : 'غیرفعال شده') }}).
            برای ادامه، از صفحهٔ <a href="{{ route('plans.index') }}" class="underline font-medium">پلن‌ها</a> آن را تمدید کنید.
        </div>
    @endunless

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- ستون اصلی --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- لینک اشتراک --}}
            <section class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-bold mb-1">لینک اشتراک (Subscription)</h2>
                <p class="text-xs text-slate-500 mb-4">
                    این لینک را در کلاینت اضافه کنید تا کانفیگ‌ها خودکار به‌روز شوند. <b>روش پیشنهادی</b>.
                </p>

                <div class="flex gap-2 mb-4">
                    <input readonly value="{{ $subUrl }}" id="sub-url"
                           class="flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs ltr font-mono">
                    <button onclick="copyText(document.getElementById('sub-url').value, this)"
                            class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm shrink-0 hover:bg-indigo-500">کپی</button>
                </div>

                <div class="flex flex-wrap items-start gap-5">
                    <div class="bg-white p-2 rounded-xl border border-slate-200">
                        <img src="{{ route('subscriptions.qr', $subscription) }}" alt="QR" class="w-40 h-40">
                    </div>
                    <div class="text-sm space-y-2">
                        <p class="text-slate-600">اسکن با دوربین کلاینت، یا دانلود مستقیم:</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('subscriptions.download', $subscription) }}"
                               class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">دانلود لیست کانفیگ</a>
                            <a href="{{ route('subscriptions.download', [$subscription, 'format' => 'clash']) }}"
                               class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">دانلود Clash (yaml)</a>
                            <a href="{{ $subUrl }}?format=clash"
                               class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">لینک Clash</a>
                        </div>
                    </div>
                </div>
            </section>

            {{-- کانفیگ‌های تکی --}}
            <section class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-bold mb-1">کانفیگ‌های تکی</h2>
                <p class="text-xs text-slate-500 mb-4">اگر کلاینت شما از لینک اشتراک پشتیبانی نمی‌کند، این‌ها را دستی وارد کنید.</p>

                @if (empty($links))
                    <p class="text-sm text-slate-500 py-6 text-center">
                        هنوز سروری به این سرویس تخصیص داده نشده است. با پشتیبانی تماس بگیرید.
                    </p>
                @else
                    <div class="space-y-3">
                        @foreach ($links as $i => $link)
                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="font-medium text-slate-700">{{ $link['remark'] }}</span>
                                        <span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 uppercase">{{ $link['protocol'] }}</span>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $link['network'] }}</span>
                                        @if ($link['security'] !== 'none')
                                            <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700">{{ $link['security'] }}</span>
                                        @endif
                                    </div>
                                    <div class="flex gap-1.5">
                                        <button onclick="copyText(document.getElementById('cfg-{{ $i }}').value, this)"
                                                class="px-2.5 py-1 rounded-lg bg-slate-800 text-white text-xs hover:bg-slate-700">کپی</button>
                                        <button onclick="toggleQr({{ $i }})"
                                                class="px-2.5 py-1 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">QR</button>
                                    </div>
                                </div>

                                <input readonly id="cfg-{{ $i }}" value="{{ $link['uri'] }}"
                                       class="w-full bg-slate-50 rounded-lg px-2 py-1.5 text-[11px] ltr font-mono border border-slate-200">

                                <div id="qr-{{ $i }}" class="hidden mt-3 flex justify-center">
                                    <img data-src="{{ route('subscriptions.qr', [$subscription, 'data' => $link['uri']]) }}"
                                         alt="QR" class="w-44 h-44">
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- ستون کناری --}}
        <aside class="space-y-5">
            <section class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-bold mb-4">وضعیت</h2>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">مصرف شده</dt>
                        <dd class="ltr font-medium">{{ \App\Support\Format::bytes($subscription->used_traffic) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">حجم کل</dt>
                        <dd class="ltr font-medium">
                            {{ $subscription->traffic_limit ? \App\Support\Format::bytes($subscription->traffic_limit) : 'نامحدود' }}
                        </dd>
                    </div>

                    @if ($subscription->traffic_limit)
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full {{ $subscription->traffic_percent > 85 ? 'bg-rose-500' : 'bg-indigo-500' }}"
                                 style="width: {{ $subscription->traffic_percent }}%"></div>
                        </div>
                        <p class="text-xs text-slate-500 text-center">
                            {{ \App\Support\Format::bytes($subscription->remaining_traffic) }} باقی‌مانده
                        </p>
                    @endif

                    <div class="flex justify-between pt-2 border-t border-slate-100">
                        <dt class="text-slate-500">تاریخ انقضا</dt>
                        <dd class="font-medium">{{ \App\Support\Format::jalali($subscription->expires_at) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">روز باقی‌مانده</dt>
                        <dd class="font-medium {{ ($subscription->days_left ?? 99) <= 3 ? 'text-rose-600' : '' }}">
                            {{ $subscription->days_left ?? '∞' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">دستگاه هم‌زمان</dt>
                        <dd class="font-medium">{{ $subscription->device_limit }}</dd>
                    </div>
                </dl>

                <a href="{{ route('plans.index') }}"
                   class="mt-5 block text-center py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">تمدید سرویس</a>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 p-5 text-sm">
                <h2 class="font-bold mb-3">راهنمای اتصال</h2>
                <ol class="space-y-2 text-slate-600 text-xs leading-6 list-decimal pr-4">
                    <li>کلاینت مناسب سیستم‌عامل خود را نصب کنید (v2rayNG اندروید، v2rayN ویندوز، Streisand/FoXray آیفون، Nekoray لینوکس).</li>
                    <li>لینک اشتراک بالا را کپی کنید.</li>
                    <li>در کلاینت گزینهٔ «Add subscription / افزودن اشتراک» را بزنید و لینک را جای‌گذاری کنید.</li>
                    <li>روی «Update / به‌روزرسانی» بزنید تا کانفیگ‌ها دریافت شوند.</li>
                    <li>سریع‌ترین سرور را با تست پینگ انتخاب و متصل شوید.</li>
                </ol>
                @if (config('panel.support.telegram'))
                    <a href="https://t.me/{{ ltrim(config('panel.support.telegram'), '@') }}"
                       class="mt-4 block text-center py-2 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">
                        پشتیبانی تلگرام
                    </a>
                @endif
            </section>
        </aside>
    </div>

    @push('scripts')
        <script>
            function toggleQr(i) {
                const box = document.getElementById('qr-' + i);
                const img = box.querySelector('img');
                if (!img.src) img.src = img.dataset.src;   // بارگذاری تنبل
                box.classList.toggle('hidden');
            }
        </script>
    @endpush
@endsection
