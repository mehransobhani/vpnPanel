@props(['subscription'])
@php
    $badge = match ($subscription->status) {
        'active' => ['bg-emerald-100 text-emerald-700', 'فعال'],
        'expired' => ['bg-amber-100 text-amber-700', 'منقضی'],
        'exhausted' => ['bg-orange-100 text-orange-700', 'اتمام حجم'],
        default => ['bg-slate-200 text-slate-600', 'غیرفعال'],
    };
@endphp
<a href="{{ route('subscriptions.show', $subscription) }}"
   class="block bg-white rounded-2xl border border-slate-200 p-5 hover:border-indigo-400 hover:shadow-sm transition">
    <div class="flex items-start justify-between mb-3">
        <div>
            <h3 class="font-bold">{{ $subscription->remark }}</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $subscription->plan?->name ?? 'سفارشی' }}</p>
        </div>
        <span class="text-xs px-2 py-1 rounded-full {{ $badge[0] }}">{{ $badge[1] }}</span>
    </div>

    <div class="space-y-2 text-sm">
        <div class="flex justify-between text-slate-600">
            <span>مصرف</span>
            <span class="ltr">
                {{ \App\Support\Format::bytes($subscription->used_traffic) }}
                @if ($subscription->traffic_limit)
                    / {{ \App\Support\Format::bytes($subscription->traffic_limit) }}
                @else
                    <span class="text-emerald-600">/ ∞</span>
                @endif
            </span>
        </div>

        @if ($subscription->traffic_limit)
            <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full {{ $subscription->traffic_percent > 85 ? 'bg-rose-500' : 'bg-indigo-500' }}"
                     style="width: {{ $subscription->traffic_percent }}%"></div>
            </div>
        @endif

        <div class="flex justify-between text-slate-600">
            <span>انقضا</span>
            <span>
                {{ \App\Support\Format::jalali($subscription->expires_at) }}
                @if ($subscription->days_left !== null)
                    <span class="text-xs {{ $subscription->days_left <= 3 ? 'text-rose-600' : 'text-slate-400' }}">
                        ({{ $subscription->days_left }} روز)
                    </span>
                @endif
            </span>
        </div>
    </div>
</a>
