<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OrderController as CustomerOrders;
use App\Models\Order;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['user', 'plan', 'subscription'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), function ($q, $needle) {
                $q->where('code', 'like', "%$needle%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%$needle%")
                        ->orWhere('name', 'like', "%$needle%"));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', ['orders' => $orders]);
    }

    /** تأیید پرداخت و تحویل خودکار سرویس */
    public function approve(Order $order, SubscriptionService $service)
    {
        if (! $order->isPending()) {
            return back()->withErrors(['order' => 'این سفارش قبلاً پردازش شده است.']);
        }

        try {
            $subscription = DB::transaction(function () use ($order, $service) {
                $order->update(['status' => Order::PAID, 'paid_at' => now()]);

                return CustomerOrders::fulfill($order, $service);
            });
        } catch (Throwable $e) {
            return back()->withErrors(['order' => 'تحویل سفارش با خطا مواجه شد: '.$e->getMessage()]);
        }

        return back()->with('status', "سفارش {$order->code} تأیید شد و سرویس #{$subscription->id} ساخته شد.");
    }

    public function reject(Request $request, Order $order)
    {
        $order->update([
            'status' => Order::CANCELED,
            'admin_note' => $request->input('admin_note'),
        ]);

        return back()->with('status', 'سفارش رد شد.');
    }
}
