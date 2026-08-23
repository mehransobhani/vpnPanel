<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\Xray\NodeClient;
use Illuminate\Console\Command;
use Throwable;

class TestNode extends Command
{
    protected $signature = 'panel:test-node';

    protected $description = 'تست اینکه سرویس Xray بالا است و پنل به آن دسترسی دارد';

    public function handle(NodeClient $client): int
    {
        $server = Server::node();

        if (! $server) {
            $this->error('نودی راه‌اندازی نشده است. `php artisan panel:setup-local-node` را اجرا کنید.');

            return self::FAILURE;
        }

        try {
            $this->info('نسخهٔ Xray: '.$client->ping($server));

            $inbounds = $client->discoverInbounds($server);
            $this->table(['tag', 'protocol', 'port'], $inbounds);

            $usage = $client->fetchUsage($server, reset: false);
            $this->info('تعداد کاربران دارای آمار: '.count($usage));

            $server->forceFill(['last_seen_at' => now(), 'last_error' => null])->saveQuietly();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $server->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->saveQuietly();

            return self::FAILURE;
        }
    }
}
