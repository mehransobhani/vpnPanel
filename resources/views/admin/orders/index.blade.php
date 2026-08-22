@extends('layouts.app')
@section('title', 'سفارش‌ها')

@section('content')
    <h1 class="text-xl font-bold mb-5">سفارش‌ها</h1>

    <form class="flex flex-wrap gap-2 mb-4">
        <input name="q" value="{{ request('q') }}" placeholder="کد سفارش، نام یا ایمیل کاربر"
               class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach (['pending' => 'در انتظار', 'paid' => 'پرداخت شده', 'canceled' => 'لغو شده'] as $k => $v)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm">فیلتر</button>
    </form>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm min-w-[900px]">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="text-right px-4 py-3">کد</th>
                    <th class="text-right px-4 py-3">کاربر</th>
                    <th class="text-right px-4 py-3">پلن</th>
                    <th class="text-right px-4 py-3">مبلغ</th>
                    <th class="text-right px-4 py-3">پیگیری</th>
                    <th class="text-right px-4 py-3">وضعیت</th>
                    <th class="text-right px-4 py-3">تاریخ</th>
                    <th class="text-right px-4 py-3">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 ltr text-xs font-mono">{{ $order->code }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.users.show', $order->user) }}" class="text-indigo-600 hover:underline">
                                {{ $order->user->name }}
                            </a>
                            <div class="text-[11px] text-slate-400 ltr">{{ $order->user->email }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $order->plan->name }}</td>
                        <td class="px-4 py-3">{{ \App\Support\Format::money($order->amount) }}</td>
                        <td class="px-4 py-3 ltr text-xs">{{ $order->reference ?: '—' }}</td>
                        <td class="px-4 py-3">@include('partials.order-status', ['status' => $order->status])</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ \App\Support\Format::jalali($order->created_at, true) }}</td>
                        <td class="px-4 py-3">
                            @if ($order->status === 'pending')
                                <div class="flex gap-1.5">
                                    <form method="POST" action="{{ route('admin.orders.approve', $order) }}"
                                          onsubmit="return confirm('پرداخت تأیید و سرویس ساخته شود؟')">
                                        @csrf
                                        <button class="px-2.5 py-1 rounded bg-emerald-600 text-white text-xs hover:bg-emerald-500">تأیید</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.orders.reject', $order) }}">
                                        @csrf
                                        <button class="px-2.5 py-1 rounded border border-rose-300 text-rose-600 text-xs hover:bg-rose-50">رد</button>
                                    </form>
                                </div>
                            @elseif ($order->subscription)
                                <a href="{{ route('admin.subscriptions.show', $order->subscription) }}"
                                   class="text-xs text-indigo-600 hover:underline">سرویس #{{ $order->subscription_id }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">سفارشی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
@endsection
