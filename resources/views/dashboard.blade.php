@extends('layouts.app')
@section('title', 'داشبورد')

@section('content')
    <div class="flex items-center justify-between mb-5">
        <h1 class="text-xl font-bold">سلام {{ auth()->user()->name }} 👋</h1>
        <a href="{{ route('plans.index') }}"
           class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">خرید سرویس جدید</a>
    </div>

    @if (auth()->user()->balance > 0)
        <div class="mb-5 rounded-xl bg-white border border-slate-200 px-4 py-3 text-sm">
            موجودی کیف پول: <b>{{ \App\Support\Format::money(auth()->user()->balance) }}</b>
        </div>
    @endif

    <h2 class="font-bold mb-3">سرویس‌های شما</h2>
    @if ($subscriptions->isEmpty())
        <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
            هنوز سرویسی ندارید.
            <a href="{{ route('plans.index') }}" class="text-indigo-600 font-medium">اولین سرویس را بخرید</a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($subscriptions as $subscription)
                <x-sub-card :subscription="$subscription" />
            @endforeach
        </div>
    @endif

    @if ($orders->isNotEmpty())
        <h2 class="font-bold mt-8 mb-3">آخرین سفارش‌ها</h2>
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs">
                    <tr>
                        <th class="text-right px-4 py-2.5">کد</th>
                        <th class="text-right px-4 py-2.5">پلن</th>
                        <th class="text-right px-4 py-2.5">مبلغ</th>
                        <th class="text-right px-4 py-2.5">وضعیت</th>
                        <th class="text-right px-4 py-2.5">تاریخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($orders as $order)
                        <tr>
                            <td class="px-4 py-2.5 ltr text-xs">{{ $order->code }}</td>
                            <td class="px-4 py-2.5">{{ $order->plan->name }}</td>
                            <td class="px-4 py-2.5">{{ \App\Support\Format::money($order->amount) }}</td>
                            <td class="px-4 py-2.5">
                                @include('partials.order-status', ['status' => $order->status])
                            </td>
                            <td class="px-4 py-2.5 text-slate-500 text-xs">{{ \App\Support\Format::jalali($order->created_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
