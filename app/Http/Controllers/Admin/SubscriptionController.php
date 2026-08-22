<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Services\Xray\LinkBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), function ($q, $needle) {
                $q->where('remark', 'like', "%$needle%")
                    ->orWhere('email_tag', 'like', "%$needle%")
                    ->orWhere('uuid', $needle)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%$needle%"));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.subscriptions.index', ['subscriptions' => $subscriptions]);
    }

    public function show(Subscription $subscription, LinkBuilder $builder)
    {
        $subscription->load(['user', 'plan', 'servers.inbounds', 'trafficLogs' => fn ($q) => $q->latest('date')->limit(30)]);

        $links = [];
        foreach ($subscription->servers as $server) {
            foreach ($server->inbounds->where('is_active', true) as $inbound) {
                $links[] = [
                    'server' => $server->name,
                    'remark' => $builder->remark($inbound, $subscription),
                    'uri' => $builder->build($inbound, $subscription),
                ];
            }
        }

        return view('admin.subscriptions.show', [
            'subscription' => $subscription,
            'links' => $links,
            'servers' => Server::orderBy('sort')->get(),
            'plans' => Plan::orderBy('sort')->get(),
        ]);
    }

    /** ساخت سرویس دستی توسط مدیر (بدون سفارش) */
    public function store(Request $request, SubscriptionService $service)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $subscription = $service->create(
            \App\Models\User::findOrFail($data['user_id']),
            Plan::findOrFail($data['plan_id']),
        );

        return redirect()->route('admin.subscriptions.show', $subscription)
            ->with('status', 'سرویس ساخته شد.');
    }

    public function update(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'remark' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in([
                Subscription::ACTIVE, Subscription::EXPIRED,
                Subscription::EXHAUSTED, Subscription::DISABLED,
            ])],
            'traffic_limit_gb' => ['required', 'numeric', 'min:0'],
            'device_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'expires_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $wasUsable = $subscription->isUsable();

        $subscription->update([
            'remark' => $data['remark'],
            'status' => $data['status'],
            'traffic_limit' => (int) round($data['traffic_limit_gb'] * 1024 ** 3),
            'device_limit' => $data['device_limit'],
            'expires_at' => $data['expires_at'],
            'note' => $data['note'],
        ]);

        // اگر وضعیت قابل‌استفاده تغییر کرده، نودها را هم به‌روز کن
        if ($wasUsable !== $subscription->refresh()->isUsable()) {
            foreach ($subscription->servers()->pluck('servers.id') as $serverId) {
                \App\Jobs\SyncSubscriptionToNode::dispatch(
                    $subscription->id,
                    (int) $serverId,
                    $subscription->isUsable() ? 'add' : 'remove',
                );
            }
        }

        return back()->with('status', 'سرویس به‌روزرسانی شد.');
    }

    public function action(Request $request, Subscription $subscription, SubscriptionService $service)
    {
        $action = $request->validate([
            'action' => ['required', Rule::in(['reset', 'rotate', 'disable', 'enable', 'renew', 'servers'])],
            'plan_id' => ['required_if:action,renew', 'nullable', 'exists:plans,id'],
            'servers' => ['required_if:action,servers', 'array'],
            'servers.*' => ['exists:servers,id'],
        ]);

        match ($action['action']) {
            'reset' => $service->resetTraffic($subscription),
            'rotate' => $service->rotateCredentials($subscription),
            'disable' => $service->disable($subscription),
            'enable' => $service->enable($subscription),
            'renew' => $service->renew($subscription, Plan::findOrFail($action['plan_id'])),
            'servers' => $service->assignServers($subscription, $action['servers']),
        };

        return back()->with('status', 'عملیات انجام شد.');
    }

    public function destroy(Subscription $subscription, SubscriptionService $service)
    {
        $service->disable($subscription); // ابتدا از نودها حذف شود
        $subscription->delete();

        return redirect()->route('admin.subscriptions.index')->with('status', 'سرویس حذف شد.');
    }
}
