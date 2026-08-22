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
    protected $signature = 'panel:sync-node {server? : شناسه یا نام سرور}';

    protected $description = 'همگام‌سازی کامل کاربران یک نود (یا همهٔ نودها)';

    public function handle(): int
    {
        $servers = Server::active()
            ->when($this->argument('server'), function ($q, $needle) {
                $q->where('id', $needle)->orWhere('name', $needle);
            })
            ->get();

        if ($servers->isEmpty()) {
            $this->error('سروری پیدا نشد.');

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
