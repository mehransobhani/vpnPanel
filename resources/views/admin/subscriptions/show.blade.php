@extends('layouts.app')
@section('title', 'سرویس #'.$subscription->id)

@php
    $field = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none';
    $label = 'block text-xs text-slate-600 mb-1.5';
@endphp

@section('content')
    <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-bold">{{ $subscription->remark }} <span class="text-slate-400 text-sm">#{{ $subscription->id }}</span></h1>
            <p class="text-sm text-slate-500">
                <a href="{{ route('admin.users.show', $subscription->user) }}" class="text-indigo-600 hover:underline">
                    {{ $subscription->user->name }}
                </a>
                — {{ $subscription->user->email }}
            </p>
        </div>
        <a href="{{ route('admin.subscriptions.index') }}" class="text-sm text-slate-500 hover:underline">← فهرست سرویس‌ها</a>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-5">
            {{-- ویرایش --}}
            <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}"
                  class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                @csrf @method('PUT')
                <h2 class="font-bold">ویرایش سرویس</h2>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">نام سرویس</label>
                        <input name="remark" required value="{{ old('remark', $subscription->remark) }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">وضعیت</label>
                        <select name="status" class="{{ $field }}">
                            @foreach (['active' => 'فعال', 'expired' => 'منقضی', 'exhausted' => 'اتمام حجم', 'disabled' => 'غیرفعال'] as $k => $v)
                                <option value="{{ $k }}" @selected($subscription->status === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $label }}">حجم کل (GB) — ۰ = نامحدود</label>
                        <input name="traffic_limit_gb" type="number" step="0.01" min="0" required class="{{ $field }} ltr"
                               value="{{ old('traffic_limit_gb', round($subscription->traffic_limit / 1024 ** 3, 2)) }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">دستگاه هم‌زمان</label>
                        <input name="device_limit" type="number" min="1" required class="{{ $field }} ltr"
                               value="{{ old('device_limit', $subscription->device_limit) }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">تاریخ انقضا (میلادی)</label>
                        <input name="expires_at" type="datetime-local" class="{{ $field }} ltr"
                               value="{{ old('expires_at', $subscription->expires_at?->format('Y-m-d\TH:i')) }}">
                        <p class="text-[11px] text-slate-400 mt-1">
                            معادل شمسی فعلی: {{ \App\Support\Format::jalali($subscription->expires_at, true) }}
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $label }}">یادداشت داخلی</label>
                        <textarea name="note" rows="2" class="{{ $field }}">{{ old('note', $subscription->note) }}</textarea>
                    </div>
                </div>

                <button class="px-6 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">ذخیره</button>
            </form>

            {{-- کانفیگ‌ها --}}
            <section class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-bold mb-3">کانفیگ‌های تولیدشده</h2>

                <div class="flex gap-2 mb-4">
                    <input readonly id="admin-sub" value="{{ route('sub', $subscription->token) }}"
                           class="flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs ltr font-mono">
                    <button onclick="copyText(document.getElementById('admin-sub').value, this)" type="button"
                            class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm shrink-0">کپی</button>
                </div>

                @forelse ($links as $i => $link)
                    <div class="mb-2">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-slate-600">{{ $link['remark'] }} <span class="text-slate-400">({{ $link['server'] }})</span></span>
                            <button type="button" onclick="copyText(document.getElementById('acfg-{{ $i }}').value, this)"
                                    class="px-2 py-0.5 rounded border border-slate-300">کپی</button>
                        </div>
                        <input readonly id="acfg-{{ $i }}" value="{{ $link['uri'] }}"
                               class="w-full bg-slate-50 rounded-lg px-2 py-1.5 text-[11px] ltr font-mono border border-slate-200">
                    </div>
                @empty
                    <p class="text-sm text-slate-500 py-4 text-center">اینباند فعالی روی سرورهای تخصیص‌داده‌شده نیست.</p>
                @endforelse
            </section>

            {{-- مصرف روزانه --}}
            @if ($subscription->trafficLogs->isNotEmpty())
                <section class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-bold mb-3">مصرف روزانه</h2>
                    <table class="w-full text-sm">
                        <thead class="text-slate-500 text-xs border-b border-slate-100">
                            <tr>
                                <th class="text-right py-2">تاریخ</th>
                                <th class="text-right py-2">آپلود</th>
                                <th class="text-right py-2">دانلود</th>
                                <th class="text-right py-2">مجموع</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($subscription->trafficLogs as $log)
                                <tr>
                                    <td class="py-2">{{ \App\Support\Format::jalali($log->date) }}</td>
                                    <td class="py-2 ltr text-xs">{{ \App\Support\Format::bytes($log->upload) }}</td>
                                    <td class="py-2 ltr text-xs">{{ \App\Support\Format::bytes($log->download) }}</td>
                                    <td class="py-2 ltr text-xs font-medium">{{ \App\Support\Format::bytes($log->upload + $log->download) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif
        </div>

        {{-- ستون کناری: عملیات --}}
        <aside class="space-y-4">
            <section class="bg-white rounded-2xl border border-slate-200 p-5 text-sm space-y-3">
                <h2 class="font-bold">اطلاعات فنی</h2>
                <div>
                    <div class="text-xs text-slate-500 mb-1">UUID</div>
                    <div class="ltr font-mono text-[11px] bg-slate-50 rounded p-2 break-all">{{ $subscription->uuid }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 mb-1">Email tag (کلید آمار Xray)</div>
                    <div class="ltr font-mono text-[11px] bg-slate-50 rounded p-2 break-all">{{ $subscription->email_tag }}</div>
                </div>
                <div class="flex justify-between pt-2 border-t border-slate-100">
                    <span class="text-slate-500">مصرف</span>
                    <span class="ltr">{{ \App\Support\Format::bytes($subscription->used_traffic) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">آخرین همگام‌سازی</span>
                    <span class="text-xs">{{ \App\Support\Format::jalali($subscription->last_synced_at, true) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">آخرین اتصال</span>
                    <span class="text-xs">{{ \App\Support\Format::jalali($subscription->last_online_at, true) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">تعداد ریست</span>
                    <span>{{ $subscription->reset_count }}</span>
                </div>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 p-5 space-y-2">
                <h2 class="font-bold mb-2">عملیات سریع</h2>

                @foreach ([
                    'reset' => ['صفر کردن مصرف', 'bg-amber-500'],
                    'rotate' => ['تعویض UUID و لینک', 'bg-violet-600'],
                    'enable' => ['فعال‌سازی', 'bg-emerald-600'],
                    'disable' => ['غیرفعال‌سازی', 'bg-slate-700'],
                ] as $act => [$text, $color])
                    <form method="POST" action="{{ route('admin.subscriptions.action', $subscription) }}"
                          onsubmit="return confirm('{{ $text }} انجام شود؟')">
                        @csrf
                        <input type="hidden" name="action" value="{{ $act }}">
                        <button class="w-full py-2 rounded-lg {{ $color }} text-white text-sm hover:opacity-90">{{ $text }}</button>
                    </form>
                @endforeach

                <form method="POST" action="{{ route('admin.subscriptions.action', $subscription) }}" class="pt-2 border-t border-slate-100">
                    @csrf
                    <input type="hidden" name="action" value="renew">
                    <label class="{{ $label }}">تمدید با پلن</label>
                    <select name="plan_id" class="{{ $field }} mb-2">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected($plan->id === $subscription->plan_id)>
                                {{ $plan->name }} ({{ $plan->duration_days }} روز)
                            </option>
                        @endforeach
                    </select>
                    <button class="w-full py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">تمدید</button>
                </form>

                <form method="POST" action="{{ route('admin.subscriptions.destroy', $subscription) }}"
                      class="pt-2 border-t border-slate-100" onsubmit="return confirm('سرویس کاملاً حذف شود؟ ابتدا از نودها پاک می‌شود.')">
                    @csrf @method('DELETE')
                    <button class="w-full py-2 rounded-lg border border-rose-300 text-rose-600 text-sm hover:bg-rose-50">حذف سرویس</button>
                </form>
            </section>
        </aside>
    </div>
@endsection
