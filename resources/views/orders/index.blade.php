@extends('layouts.app')
@section('title', 'سفارش‌ها')

@section('content')
    <h1 class="text-xl font-bold mb-5">سفارش‌های من</h1>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm min-w-[600px]">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="text-right px-4 py-3">کد</th>
                    <th class="text-right px-4 py-3">پلن</th>
                    <th class="text-right px-4 py-3">مبلغ</th>
                    <th class="text-right px-4 py-3">وضعیت</th>
                    <th class="text-right px-4 py-3">تاریخ</th>
                    <th class="text-right px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr>
                        <td class="px-4 py-3 ltr text-xs font-mono">{{ $order->code }}</td>
                        <td class="px-4 py-3">{{ $order->plan->name }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Format::money($order->amount) }}</td>
                        <td class="px-4 py-3">@include('partials.order-status', ['status' => $order->status])</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ \App\Support\Format::jalali($order->created_at, true) }}</td>
                        <td class="px-4 py-3 text-left">
                            @if ($order->isPending())
                                <a href="{{ route('orders.pay', $order) }}" class="text-indigo-600 text-xs hover:underline">پرداخت</a>
                            @elseif ($order->subscription)
                                <a href="{{ route('subscriptions.show', $order->subscription) }}" class="text-emerald-600 text-xs hover:underline">
                                    مشاهدهٔ سرویس
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">سفارشی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
@endsection
