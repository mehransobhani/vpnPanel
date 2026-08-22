@extends('layouts.app')
@section('title', 'پرداخت سفارش')

@section('content')
    <div class="max-w-xl mx-auto">
        <h1 class="text-xl font-bold mb-1">پرداخت سفارش</h1>
        <p class="text-sm text-slate-500 mb-5 ltr font-mono">{{ $order->code }}</p>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">پلن</dt><dd class="font-medium">{{ $order->plan->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">مدت</dt><dd>{{ $order->plan->duration_days }} روز</dd></div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">حجم</dt>
                    <dd>{{ $order->plan->traffic_gb ? $order->plan->traffic_gb.' GB' : 'نامحدود' }}</dd>
                </div>
                <div class="flex justify-between pt-3 border-t border-slate-100 text-base">
                    <dt class="font-medium">مبلغ قابل پرداخت</dt>
                    <dd class="font-bold text-indigo-600">{{ \App\Support\Format::money($order->amount) }}</dd>
                </div>
            </dl>
        </div>

        @if (config('panel.payment.card_number'))
            <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4">
                <h2 class="font-bold mb-3">اطلاعات کارت‌به‌کارت</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">شمارهٔ کارت</span>
                        <span class="flex items-center gap-2">
                            <b class="ltr font-mono" id="card">{{ config('panel.payment.card_number') }}</b>
                            <button onclick="copyText(document.getElementById('card').innerText, this)"
                                    class="px-2 py-0.5 rounded border border-slate-300 text-xs">کپی</button>
                        </span>
                    </div>
                    <div class="flex justify-between"><span class="text-slate-500">به نام</span><b>{{ config('panel.payment.card_holder') }}</b></div>
                    @if (config('panel.payment.bank'))
                        <div class="flex justify-between"><span class="text-slate-500">بانک</span><b>{{ config('panel.payment.bank') }}</b></div>
                    @endif
                </div>
            </div>
        @endif

        @if ($order->isPending())
            <form method="POST" action="{{ route('orders.receipt', $order) }}"
                  class="bg-white rounded-2xl border border-slate-200 p-5">
                @csrf
                <h2 class="font-bold mb-1">ثبت رسید پرداخت</h2>
                <p class="text-xs text-slate-500 mb-4">
                    مبلغ را واریز کنید و شمارهٔ پیگیری/۴ رقم آخر کارت مبدأ را اینجا وارد کنید.
                    پس از تأیید مدیر، سرویس فعال می‌شود.
                </p>

                <input name="reference" required value="{{ old('reference', $order->reference) }}"
                       placeholder="شمارهٔ پیگیری تراکنش"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm mb-3 ltr">

                <button class="w-full py-2.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">
                    ثبت رسید
                </button>
            </form>
        @else
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 text-sm text-emerald-800">
                این سفارش پردازش شده است.
                @if ($order->subscription)
                    <a href="{{ route('subscriptions.show', $order->subscription) }}" class="underline font-medium">مشاهدهٔ سرویس</a>
                @endif
            </div>
        @endif
    </div>
@endsection
