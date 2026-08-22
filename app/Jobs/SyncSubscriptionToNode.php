<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\Subscription;
use App\Services\Xray\NodeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * افزودن یا حذف یک سرویس روی یک نود Xray.
 *
 * شناسه‌ها (نه مدل‌ها) پاس داده می‌شوند تا اگر رکورد حذف شد، job به‌جای
 * ModelNotFound صرفاً بی‌صدا خارج شود.
 */
class SyncSubscriptionToNode implements ShouldQueue
{
    use Queueable;

    /**
     * نامحدود، ولی محدود به بازهٔ retryUntil.
     * چون WithoutOverlapping هنگام برخورد قفل job را release می‌کند،
     * شمردن تلاش‌ها باعث fail شدن بی‌دلیل کارهای سالم می‌شود.
     */
    public int $tries = 0;

    public array $backoff = [10, 60, 180];

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(15);
    }

    public function __construct(
        public int $subscriptionId,
        public int $serverId,
        public string $action = 'add', // add | remove
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("node-sync:{$this->serverId}"))
                ->releaseAfter(5)
                ->expireAfter(180),
        ];
    }

    public function handle(NodeClient $client): void
    {
        $subscription = Subscription::find($this->subscriptionId);
        $server = Server::find($this->serverId);

        if (! $subscription || ! $server) {
            return;
        }

        if ($server->sync_driver === 'manual') {
            $this->markPivot($subscription, $server, 'pending', 'سرور در حالت دستی است.');

            return;
        }

        // سرویس غیرفعال/منقضی نباید روی نود بماند
        $action = ($this->action === 'add' && ! $subscription->isUsable()) ? 'remove' : $this->action;

        try {
            if ($action === 'add') {
                $client->removeUser($server, $subscription); // جلوگیری از duplicate
                $client->addUser($server, $subscription);
                $this->markPivot($subscription, $server, 'synced');
            } else {
                $client->removeUser($server, $subscription);
                $this->markPivot($subscription, $server, 'removed');
            }

            $subscription->forceFill(['last_synced_at' => now()])->saveQuietly();
            $server->forceFill(['last_seen_at' => now(), 'last_error' => null])->saveQuietly();
        } catch (Throwable $e) {
            $this->markPivot($subscription, $server, 'failed', $e->getMessage());
            $server->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->saveQuietly();

            Log::warning('node sync failed', [
                'server' => $server->name,
                'subscription' => $subscription->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function markPivot(Subscription $subscription, Server $server, string $state, ?string $message = null): void
    {
        if (! $subscription->servers()->whereKey($server->id)->exists()) {
            return;
        }

        $subscription->servers()->updateExistingPivot($server->id, [
            'state' => $state,
            'message' => $message ? mb_substr($message, 0, 250) : null,
            'synced_at' => now(),
        ]);
    }
}
