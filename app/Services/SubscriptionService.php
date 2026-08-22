<?php

namespace App\Services;

use App\Jobs\SyncSubscriptionToNode;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    /**
     * ساخت سرویس جدید برای کاربر بر اساس پلن، و صف‌کردن همگام‌سازی با نودها.
     */
    public function create(User $user, Plan $plan, ?Order $order = null): Subscription
    {
        $subscription = DB::transaction(function () use ($user, $plan, $order) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'uuid' => (string) Str::uuid(),
                'password' => Str::random(24),
                'token' => $this->uniqueToken(),
                'remark' => $plan->name,
                'email_tag' => $this->uniqueEmailTag($user),
                'status' => Subscription::ACTIVE,
                'traffic_limit' => $plan->traffic_bytes,
                'device_limit' => $plan->device_limit,
                'started_at' => now(),
                'expires_at' => now()->addDays($plan->duration_days),
            ]);

            $subscription->servers()->sync(
                $this->serversForPlan($plan)->pluck('id')->all()
            );

            $order?->update(['subscription_id' => $subscription->id]);

            return $subscription;
        });

        ActivityLog::record('subscription.created', $subscription, ['plan' => $plan->slug]);

        $this->dispatchSync($subscription, 'add');

        return $subscription;
    }

    /**
     * تمدید سرویس: اگر منقضی شده از امروز، وگرنه از تاریخ انقضای فعلی ادامه می‌دهد.
     */
    public function renew(Subscription $subscription, Plan $plan, bool $resetTraffic = true): Subscription
    {
        $base = $subscription->expires_at?->isFuture() ? $subscription->expires_at : now();

        $subscription->update([
            'plan_id' => $plan->id,
            'status' => Subscription::ACTIVE,
            'traffic_limit' => $plan->traffic_bytes,
            'device_limit' => $plan->device_limit,
            'expires_at' => $base->copy()->addDays($plan->duration_days),
            'started_at' => $subscription->started_at ?? now(),
            'upload' => $resetTraffic ? 0 : $subscription->upload,
            'download' => $resetTraffic ? 0 : $subscription->download,
        ]);

        $subscription->servers()->syncWithoutDetaching(
            $this->serversForPlan($plan)->pluck('id')->all()
        );

        ActivityLog::record('subscription.renewed', $subscription, ['plan' => $plan->slug]);

        $this->dispatchSync($subscription, 'add');

        return $subscription->refresh();
    }

    /**
     * صفر کردن مصرف بدون تغییر تاریخ انقضا.
     */
    public function resetTraffic(Subscription $subscription): void
    {
        $subscription->update([
            'upload' => 0,
            'download' => 0,
            'reset_count' => $subscription->reset_count + 1,
            'status' => $subscription->status === Subscription::EXHAUSTED
                ? Subscription::ACTIVE
                : $subscription->status,
        ]);

        ActivityLog::record('subscription.traffic_reset', $subscription);

        $this->dispatchSync($subscription, 'add');
    }

    /**
     * تعویض UUID/رمز — وقتی کانفیگ کاربر لو رفته باشد.
     */
    public function rotateCredentials(Subscription $subscription): void
    {
        $this->dispatchSync($subscription, 'remove');

        $subscription->update([
            'uuid' => (string) Str::uuid(),
            'password' => Str::random(24),
            'token' => $this->uniqueToken(),
        ]);

        ActivityLog::record('subscription.rotated', $subscription);

        $this->dispatchSync($subscription, 'add');
    }

    public function disable(Subscription $subscription, string $status = Subscription::DISABLED): void
    {
        $subscription->update(['status' => $status]);

        ActivityLog::record('subscription.disabled', $subscription, ['status' => $status]);

        $this->dispatchSync($subscription, 'remove');
    }

    public function enable(Subscription $subscription): void
    {
        $subscription->update(['status' => Subscription::ACTIVE]);

        ActivityLog::record('subscription.enabled', $subscription);

        $this->dispatchSync($subscription, 'add');
    }

    /**
     * تخصیص دستی سرورها به یک سرویس.
     */
    public function assignServers(Subscription $subscription, array $serverIds): void
    {
        $removed = $subscription->servers()->pluck('servers.id')->diff($serverIds);

        foreach ($removed as $serverId) {
            SyncSubscriptionToNode::dispatch($subscription->id, $serverId, 'remove');
        }

        $subscription->servers()->sync($serverIds);

        foreach ($serverIds as $serverId) {
            SyncSubscriptionToNode::dispatch($subscription->id, (int) $serverId, 'add');
        }
    }

    private function dispatchSync(Subscription $subscription, string $action): void
    {
        foreach ($subscription->servers()->pluck('servers.id') as $serverId) {
            SyncSubscriptionToNode::dispatch($subscription->id, (int) $serverId, $action);
        }
    }

    /**
     * اگر پلن سرور مشخصی نداشته باشد، همهٔ سرورهای فعال و پرنشده را برمی‌گرداند.
     */
    private function serversForPlan(Plan $plan)
    {
        $servers = $plan->servers()->active()->get();

        if ($servers->isEmpty()) {
            $servers = Server::active()->get();
        }

        return $servers->reject(fn (Server $s) => $s->is_full);
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::lower(Str::random(32));
        } while (Subscription::where('token', $token)->exists());

        return $token;
    }

    /**
     * email در Xray کلید یکتای شمارش مصرف است؛ باید در کل نود یکتا بماند.
     */
    private function uniqueEmailTag(User $user): string
    {
        $base = Str::slug(Str::before($user->email, '@')) ?: 'user';

        do {
            $tag = $base.'-'.Str::lower(Str::random(6)).'@'.config('panel.email_domain');
        } while (Subscription::where('email_tag', $tag)->exists());

        return $tag;
    }
}
