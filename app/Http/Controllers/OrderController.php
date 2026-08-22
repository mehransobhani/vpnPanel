<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return view('orders.index', [
            'orders' => $request->user()->orders()->with(['plan', 'subscription'])->latest()->paginate(15),
        ]);
    }

    /** ثبت سفارش خرید یا تمدید */
    public function store(Request $request, SubscriptionService $service)
    {
        $data = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'subscription' => ['nullable', 'exists:subscriptions,token'],
            'payment_method' => ['required', 'in:wallet,manual'],
        ]);

        $plan = Plan::active()->where('slug', $data['plan'])->firstOrFail();
        $user = $request->user();

        $subscription = null;
        if (! empty($data['subscription'])) {
            $subscription = $user->subscriptions()->where('token', $data['subscription'])->firstOrFail();
        }

        $order = Order::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'subscription_id' => $subscription?->id,
            'amount' => $plan->price,
            'payment_method' => $data['payment_method'],
        ]);

        // پرداخت از کیف پول: فوراً تحویل بده
        if ($data['payment_method'] === 'wallet') {
            if ($user->balance < $plan->price) {
                $order->update(['status' => Order::CANCELED, 'admin_note' => 'موجودی کیف پول کافی نبود.']);

                return back()->withErrors(['wallet' => 'موجودی کیف پول کافی نیست.']);
            }

            DB::transaction(function () use ($user, $plan, $order) {
                $user->decrement('balance', $plan->price);
                $order->update(['status' => Order::PAID, 'paid_at' => now()]);
            });

            $this->fulfill($order, $service);

            return redirect()->route('subscriptions.show', $order->refresh()->subscription)
                ->with('status', 'سرویس شما فعال شد.');
        }

        return redirect()->route('orders.pay', $order)
            ->with('status', 'سفارش ثبت شد. لطفاً پرداخت را انجام و رسید را ثبت کنید.');
    }

    public function pay(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return view('orders.pay', ['order' => $order->load('plan')]);
    }

    /** ثبت شمارهٔ پیگیری کارت‌به‌کارت توسط کاربر */
    public function submitReceipt(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->isPending(), 422, 'این سفارش قابل ویرایش نیست.');

        $data = $request->validate([
            'reference' => ['required', 'string', 'max:120'],
        ]);

        $order->update($data);

        ActivityLog::record('order.receipt_submitted', $order);

        return redirect()->route('orders.index')
            ->with('status', 'رسید ثبت شد. پس از تأیید مدیر، سرویس فعال می‌شود.');
    }

    /**
     * تحویل سفارش پرداخت‌شده: ساخت سرویس جدید یا تمدید سرویس موجود.
     */
    public static function fulfill(Order $order, SubscriptionService $service): Subscription
    {
        $subscription = $order->subscription
            ? $service->renew($order->subscription, $order->plan)
            : $service->create($order->user, $order->plan, $order);

        $order->update(['subscription_id' => $subscription->id]);

        ActivityLog::record('order.fulfilled', $order, ['subscription' => $subscription->id]);

        return $subscription;
    }
}
