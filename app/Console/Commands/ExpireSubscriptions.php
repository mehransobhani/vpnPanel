<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * سرویس‌های منقضی‌شده را علامت می‌زند و از روی نودها پاک می‌کند.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'panel:expire';

    protected $description = 'غیرفعال‌سازی سرویس‌های منقضی‌شده';

    public function handle(SubscriptionService $service): int
    {
        $count = 0;

        Subscription::active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($subscriptions) use ($service, &$count) {
                foreach ($subscriptions as $subscription) {
                    $service->disable($subscription, Subscription::EXPIRED);
                    $count++;
                }
            });

        $this->info("$count سرویس منقضی شد.");

        return self::SUCCESS;
    }
}
