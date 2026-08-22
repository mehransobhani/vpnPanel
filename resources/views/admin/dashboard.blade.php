@extends('layouts.app')
@section('title', 'داشبورد مدیریت')

@section('content')
    <h1 class="text-xl font-bold mb-5">داشبورد مدیریت</h1>

    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-6">
        @php
            $cards = [
                ['کاربران', number_format($stats['users']), 'bg-slate-800'],
                ['سرویس فعال', number_format($stats['active_subs']), 'bg-emerald-600'],
                ['نزدیک انقضا', number_format($stats['expiring_soon']), 'bg-amber-500'],
                ['سفارش در انتظار', number_format($stats['pending_orders']), 'bg-rose-500'],
                ['سرورها', $stats['servers'].($stats['offline_servers'] ? " ({$stats['offline_servers']} آفلاین)" : ''), 'bg-indigo-600'],
                ['درآمد این ماه', \App\Support\Format::money($stats['revenue_month']), 'bg-violet-600'],
                ['ترافیک امروز', \App\Support\Format::bytes($stats['traffic_today']), 'bg-cyan-600'],
            ];
        @endphp
        @foreach ($cards as [$label, $value, $color])
            <div class="{{ $color }} text-white rounded-2xl p-4">
                <p class="text-xs opacity-80 mb-1">{{ $label }}</p>
                <p class="text-lg font-bold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-bold mb-4">ترافیک ۱۴ روز اخیر</h2>
            @if ($chart->isEmpty())
                <p class="text-sm text-slate-500 py-8 text-center">هنوز داده‌ای ثبت نشده است.</p>
            @else
                @php $max = max($chart->toArray()) ?: 1; @endphp
                <div class="flex items-end gap-1.5 h-40">
                    @foreach ($chart as $date => $total)
                        <div class="flex-1 flex flex-col items-center gap-1 group">
                            <span class="text-[10px] text-slate-500 opacity-0 group-hover:opacity-100 whitespace-nowrap">
                                {{ \App\Support\Format::bytes($total, 1) }}
                            </span>
                            <div class="w-full bg-indigo-500 rounded-t hover:bg-indigo-600 transition"
                                 style="height: {{ max(2, round($total / $max * 100)) }}%"></div>
                            <span class="text-[9px] text-slate-400">{{ substr($date, 8, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-bold mb-4">وضعیت سرورها</h2>
            <div class="space-y-2">
                @forelse ($servers as $server)
                    @php $online = $server->last_seen_at && $server->last_seen_at->gt(now()->subHour()); @endphp
                    <div class="flex items-center justify-between text-sm py-2 border-b border-slate-100 last:border-0">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $online ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                            <a href="{{ route('admin.servers.edit', $server) }}" class="hover:underline">{{ $server->name }}</a>
                            @unless ($server->is_active)
                                <span class="text-xs text-slate-400">(غیرفعال)</span>
                            @endunless
                        </div>
                        <span class="text-xs text-slate-500">{{ $server->subscriptions_count }} سرویس</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 py-6 text-center">
                        <a href="{{ route('admin.servers.create') }}" class="text-indigo-600">اولین سرور را اضافه کنید</a>
                    </p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="bg-white rounded-2xl border border-slate-200 mt-5 overflow-x-auto">
        <h2 class="font-bold p-5 pb-3">آخرین سفارش‌ها</h2>
        <table class="w-full text-sm min-w-[600px]">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="text-right px-4 py-2.5">کد</th>
                    <th class="text-right px-4 py-2.5">کاربر</th>
                    <th class="text-right px-4 py-2.5">پلن</th>
                    <th class="text-right px-4 py-2.5">مبلغ</th>
                    <th class="text-right px-4 py-2.5">وضعیت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recentOrders as $order)
                    <tr>
                        <td class="px-4 py-2.5 ltr text-xs font-mono">{{ $order->code }}</td>
                        <td class="px-4 py-2.5">
                            <a href="{{ route('admin.users.show', $order->user) }}" class="hover:underline">{{ $order->user->name }}</a>
                        </td>
                        <td class="px-4 py-2.5">{{ $order->plan->name }}</td>
                        <td class="px-4 py-2.5">{{ \App\Support\Format::money($order->amount) }}</td>
                        <td class="px-4 py-2.5">@include('partials.order-status', ['status' => $order->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">سفارشی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
