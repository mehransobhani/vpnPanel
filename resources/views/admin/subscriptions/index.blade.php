@extends('layouts.app')
@section('title', 'سرویس‌ها')

@section('content')
    <h1 class="text-xl font-bold mb-5">سرویس‌ها</h1>

    <form class="flex flex-wrap gap-2 mb-4">
        <input name="q" value="{{ request('q') }}" placeholder="نام سرویس، UUID، ایمیل کاربر یا email tag"
               class="flex-1 min-w-[220px] rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach (['active' => 'فعال', 'expired' => 'منقضی', 'exhausted' => 'اتمام حجم', 'disabled' => 'غیرفعال'] as $k => $v)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm">فیلتر</button>
    </form>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm min-w-[850px]">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="text-right px-4 py-3">#</th>
                    <th class="text-right px-4 py-3">سرویس</th>
                    <th class="text-right px-4 py-3">کاربر</th>
                    <th class="text-right px-4 py-3">مصرف</th>
                    <th class="text-right px-4 py-3">انقضا</th>
                    <th class="text-right px-4 py-3">وضعیت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($subscriptions as $sub)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-xs text-slate-400">{{ $sub->id }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.subscriptions.show', $sub) }}" class="font-medium text-indigo-600 hover:underline">
                                {{ $sub->remark }}
                            </a>
                            <div class="text-[11px] text-slate-400">{{ $sub->plan?->name }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <a href="{{ route('admin.users.show', $sub->user_id) }}" class="hover:underline">{{ $sub->user->name }}</a>
                        </td>
                        <td class="px-4 py-3 ltr text-xs">
                            {{ \App\Support\Format::bytes($sub->used_traffic) }}
                            {{ $sub->traffic_limit ? '/ '.\App\Support\Format::bytes($sub->traffic_limit) : '/ ∞' }}
                            @if ($sub->traffic_limit)
                                <div class="h-1 bg-slate-100 rounded-full mt-1 w-24">
                                    <div class="h-full rounded-full {{ $sub->traffic_percent > 85 ? 'bg-rose-500' : 'bg-indigo-500' }}"
                                         style="width: {{ $sub->traffic_percent }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            {{ \App\Support\Format::jalali($sub->expires_at) }}
                            @if ($sub->days_left !== null)
                                <span class="{{ $sub->days_left <= 3 ? 'text-rose-600' : 'text-slate-400' }}">({{ $sub->days_left }}د)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                [$cls, $lbl] = match ($sub->status) {
                                    'active' => ['bg-emerald-100 text-emerald-700', 'فعال'],
                                    'expired' => ['bg-amber-100 text-amber-700', 'منقضی'],
                                    'exhausted' => ['bg-orange-100 text-orange-700', 'اتمام حجم'],
                                    default => ['bg-slate-200 text-slate-600', 'غیرفعال'],
                                };
                            @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $cls }}">{{ $lbl }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">سرویسی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $subscriptions->links() }}</div>
@endsection
