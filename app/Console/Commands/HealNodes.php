<?php

namespace App\Console\Commands;

use App\Jobs\SyncSubscriptionToNode;
use App\Models\Server;
use App\Services\Xray\NodeClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * کاربران Xray فقط در حافظه‌اند؛ با هر ری‌استارت نود پاک می‌شوند.
 *
 * این دستور تعداد کاربرانِ روی نود را با تعداد سرویس‌های فعال پنل مقایسه می‌کند
 * و اگر کمتر بود، فقط همان نود را دوباره همگام می‌کند. چون یک فراخوانی سبک
 * به ازای هر اینباند است، می‌توان مرتب اجرایش کرد.
 */
class HealNodes extends Command
{
    protected $signature = 'panel:heal-nodes';

    protected $description = 'بازگرداندن کاربران به نود پس از ری‌استارت Xray';

    public function handle(NodeClient $client): int
    {
        $servers = Server::active()->with('inbounds')->get();

        $healed = 0;

        foreach ($servers as $server) {
            $expected = $server->subscriptions()->active()->count();

            if ($expected === 0) {
                continue;
            }

            $inbounds = $server->inbounds->where('is_active', true);
            $missing = false;

            foreach ($inbounds as $inbound) {
                try {
                    $onNode = $client->countUsers($server, $inbound);
                } catch (Throwable $e) {
                    // نود در دسترس نیست — کار sync-usage است که خطا را گزارش کند.
                    $this->warn("[{$server->name}/{$inbound->tag}] {$e->getMessage()}");

                    continue 2;
                }

                if ($onNode < $expected) {
                    $this->line("[{$server->name}/{$inbound->tag}] روی نود $onNode از $expected کاربر — نیاز به ترمیم.");
                    $missing = true;
                    break;
                }
            }

            if (! $missing) {
                continue;
            }

            foreach ($server->subscriptions()->active()->pluck('subscriptions.id') as $id) {
                SyncSubscriptionToNode::dispatch((int) $id, $server->id, 'add');
            }

            $healed++;
            $this->info("[{$server->name}] $expected سرویس برای همگام‌سازی در صف قرار گرفت.");
        }

        $this->info($healed === 0 ? 'همهٔ نودها همگام هستند.' : "$healed نود ترمیم شد.");

        return self::SUCCESS;
    }
}
