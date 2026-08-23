<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\Subscription;
use App\Models\TrafficLog;
use App\Services\SubscriptionService;
use App\Services\Xray\NodeClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * مصرف کاربران را از همهٔ نودها می‌خواند، در پنل جمع می‌زند و
 * سرویس‌هایی که حجمشان تمام شده را غیرفعال می‌کند.
 */
class SyncUsage extends Command
{
    protected $signature = 'panel:sync-usage';

    protected $description = 'خواندن آمار مصرف از Xray و به‌روزرسانی سرویس‌ها';

    public function handle(NodeClient $client, SubscriptionService $service): int
    {
        $servers = Server::active()->get();

        if ($servers->isEmpty()) {
            $this->warn('نودی راه‌اندازی نشده است. `php artisan panel:setup-local-node` را اجرا کنید.');

            return self::SUCCESS;
        }

        $touched = collect();

        foreach ($servers as $server) {
            try {
                $usage = $client->fetchUsage($server, reset: true);
            } catch (Throwable $e) {
                $server->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->saveQuietly();
                $this->error("[{$server->name}] {$e->getMessage()}");

                continue;
            }

            $server->forceFill(['last_seen_at' => now(), 'last_error' => null])->saveQuietly();

            if (! $usage) {
                $this->line("[{$server->name}] مصرف جدیدی ثبت نشد.");

                continue;
            }

            $subscriptions = Subscription::whereIn('email_tag', array_keys($usage))
                ->get()
                ->keyBy('email_tag');

            DB::transaction(function () use ($usage, $subscriptions, $server, &$touched) {
                foreach ($usage as $email => $bytes) {
                    $subscription = $subscriptions->get($email);

                    if (! $subscription || ($bytes['up'] + $bytes['down']) === 0) {
                        continue;
                    }

                    // شمارنده‌های نود با -reset صفر شده‌اند، پس مقادیر «دلتا» هستند.
                    $subscription->increment('upload', $bytes['up']);
                    $subscription->increment('download', $bytes['down']);
                    $subscription->forceFill(['last_online_at' => now()])->saveQuietly();

                    // اگر ردیف امروز وجود دارد جمع بزن، وگرنه بساز.
                    $affected = TrafficLog::query()
                        ->where('subscription_id', $subscription->id)
                        ->where('server_id', $server->id)
                        ->where('date', now()->toDateString())
                        ->update([
                            'upload' => DB::raw('upload + '.(int) $bytes['up']),
                            'download' => DB::raw('download + '.(int) $bytes['down']),
                            'updated_at' => now(),
                        ]);

                    if ($affected === 0) {
                        TrafficLog::create([
                            'subscription_id' => $subscription->id,
                            'server_id' => $server->id,
                            'date' => now()->toDateString(),
                            'upload' => $bytes['up'],
                            'download' => $bytes['down'],
                        ]);
                    }

                    $touched->put($subscription->id, $subscription);
                }
            });

            $this->info("[{$server->name}] مصرف ".count($usage).' کاربر دریافت شد.');
        }

        $exhausted = 0;

        foreach ($touched as $subscription) {
            $subscription->refresh();

            if ($subscription->traffic_limit > 0
                && $subscription->used_traffic >= $subscription->traffic_limit
                && $subscription->status === Subscription::ACTIVE) {
                $service->disable($subscription, Subscription::EXHAUSTED);
                $exhausted++;
            }
        }

        $this->info("پایان. سرویس‌های اتمام‌حجم: $exhausted");

        return self::SUCCESS;
    }
}
