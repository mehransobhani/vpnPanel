@php
    [$class, $label] = match ($status) {
        'paid' => ['bg-emerald-100 text-emerald-700', 'پرداخت شده'],
        'pending' => ['bg-amber-100 text-amber-700', 'در انتظار پرداخت'],
        'canceled' => ['bg-rose-100 text-rose-700', 'لغو شده'],
        'refunded' => ['bg-slate-200 text-slate-600', 'مسترد شده'],
        default => ['bg-slate-100 text-slate-600', $status],
    };
@endphp
<span class="text-xs px-2 py-0.5 rounded-full {{ $class }}">{{ $label }}</span>
