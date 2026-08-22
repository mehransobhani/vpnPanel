<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\TrafficLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $traffic = TrafficLog::where('date', '>=', now()->subDays(13)->toDateString())
            ->select('date', DB::raw('SUM(upload + download) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'active_subs' => Subscription::active()->count(),
                'expiring_soon' => Subscription::active()
                    ->whereBetween('expires_at', [now(), now()->addDays(config('panel.expiry_warning_days'))])
                    ->count(),
                'servers' => Server::count(),
                'offline_servers' => Server::active()
                    ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subHour()))
                    ->count(),
                'pending_orders' => Order::where('status', Order::PENDING)->count(),
                'revenue_month' => Order::where('status', Order::PAID)
                    ->where('paid_at', '>=', now()->startOfMonth())
                    ->sum('amount'),
                'traffic_today' => TrafficLog::where('date', now()->toDateString())
                    ->sum(DB::raw('upload + download')),
            ],
            'chart' => $traffic,
            'recentOrders' => Order::with(['user', 'plan'])->latest()->limit(10)->get(),
            'servers' => Server::withCount('subscriptions')->orderBy('sort')->get(),
        ]);
    }
}
