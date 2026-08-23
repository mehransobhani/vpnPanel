<?php

namespace App\Console\Commands;

use App\Jobs\SyncSubscriptionToNode;
use App\Models\Server;
use Illuminate\Console\Command;

/**
 * همهٔ سرویس‌های فعالِ یک نود را دوباره روی آن می‌نویسد.
 * بعد از ری‌استارت/بازنصب Xray روی نود این را اجرا کنید.
 */
class SyncNode extends Command
{
    protected $signature = 'panel:sync-node';

    protected $description = 'بازنویسی همهٔ کاربران فعال روی نود Xray';

    public function handle(): int
    {
        $servers = Server::active()->get();

        if ($servers->isEmpty()) {
            $this->error('نودی راه‌اندازی نشده است. `php artisan panel:setup-local-node` را اجرا کنید.');

            return self::FAILURE;
        }

        $queued = 0;

        foreach ($servers as $server) {
            $subscriptions = $server->subscriptions()->active()->get();

            foreach ($subscriptions as $subscription) {
                SyncSubscriptionToNode::dispatch($subscription->id, $server->id, 'add');
                $queued++;
            }

            $this->info("[{$server->name}] {$subscriptions->count()} سرویس در صف قرار گرفت.");
        }

        $this->info("مجموع $queued کار در صف. با `php artisan queue:work` پردازش می‌شوند.");

        return self::SUCCESS;
    }
}
