<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\Xray\NodeClient;
use Illuminate\Console\Command;
use Throwable;

class TestNode extends Command
{
    protected $signature = 'panel:test-node {server : شناسه یا نام سرور}';

    protected $description = 'تست اتصال SSH و API به یک نود Xray';

    public function handle(NodeClient $client): int
    {
        $needle = $this->argument('server');
        $server = Server::where('id', $needle)->orWhere('name', $needle)->first();

        if (! $server) {
            $this->error('سروری با این شناسه/نام پیدا نشد.');

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
