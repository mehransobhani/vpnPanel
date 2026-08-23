@php
    $isAdminArea = request()->routeIs('admin.*');
    $links = $isAdminArea ? [
        ['admin.dashboard', 'داشبورد'],
        ['admin.node', 'نود VPN'],
        ['admin.plans.index', 'پلن‌ها'],
        ['admin.subscriptions.index', 'سرویس‌ها'],
        ['admin.orders.index', 'سفارش‌ها'],
        ['admin.users.index', 'کاربران'],
    ] : [
        ['dashboard', 'داشبورد'],
        ['subscriptions.index', 'سرویس‌های من'],
        ['plans.index', 'خرید سرویس'],
        ['orders.index', 'سفارش‌ها'],
    ];
@endphp
<nav class="bg-white border-b border-slate-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-14 gap-4">
            <div class="flex items-center gap-1 overflow-x-auto">
                <span class="font-bold text-indigo-600 ml-3 whitespace-nowrap">{{ config('panel.brand') }}</span>
                @foreach ($links as [$route, $label])
                    <a href="{{ route($route) }}"
                       class="px-3 py-1.5 rounded-lg text-sm whitespace-nowrap transition
                              {{ request()->routeIs($route) || request()->routeIs(str_replace('index', '*', $route))
                                 ? 'bg-indigo-50 text-indigo-700 font-medium'
                                 : 'text-slate-600 hover:bg-slate-100' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @if (auth()->user()->isAdmin())
                    <a href="{{ $isAdminArea ? route('dashboard') : route('admin.dashboard') }}"
                       class="text-xs px-2.5 py-1.5 rounded-lg bg-slate-800 text-white hover:bg-slate-700">
                        {{ $isAdminArea ? 'پنل کاربر' : 'پنل مدیریت' }}
                    </a>
                @endif
                <span class="text-xs text-slate-500 hidden sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-xs text-rose-600 hover:underline">خروج</button>
                </form>
            </div>
        </div>
    </div>
</nav>
