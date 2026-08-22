<?php

namespace Database\Seeders;

use App\Models\Inbound;
use App\Models\Plan;
use App\Models\Server;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\Xray\LinkBuilder;
use Illuminate\Database\Seeder;

/**
 * دادهٔ نمونه برای دیدن سریع خروجی پنل بدون داشتن سرور واقعی.
 * سرور با درایور «دستی» ساخته می‌شود تا تلاشی برای SSH انجام نشود.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $server = Server::updateOrCreate(['name' => 'آلمان ۱'], [
            'address' => 'de1.example.com',
            'country' => 'de',
            'sync_driver' => 'manual',
            'is_active' => true,
            'sort' => 1,
            'note' => 'سرور نمونه — قبل از فروش واقعی آن را با سرور خودتان جایگزین کنید.',
        ]);

        $inbounds = [
            ['vless-reality', [
                'protocol' => 'vless', 'port' => 443, 'network' => 'tcp', 'security' => 'reality',
                'sni' => 'www.microsoft.com', 'flow' => 'xtls-rprx-vision', 'fingerprint' => 'chrome',
                'reality_public_key' => '8k2f_pQ0Xr9vN3LsTuVwYzAbCdEfGhIjKlMnOpQrStU',
                'reality_short_id' => 'a1b2c3d4', 'reality_spider_x' => '/',
                'remark_template' => '{brand}-{country}-{protocol}', 'sort' => 1,
            ]],
            ['vmess-ws', [
                'protocol' => 'vmess', 'port' => 8443, 'network' => 'ws', 'security' => 'tls',
                'sni' => 'cdn.example.com', 'host' => 'cdn.example.com', 'path' => '/ray',
                'fingerprint' => 'chrome', 'remark_template' => '{brand}-{server}-WS', 'sort' => 2,
            ]],
            ['trojan-grpc', [
                'protocol' => 'trojan', 'port' => 2053, 'network' => 'grpc', 'security' => 'tls',
                'sni' => 'grpc.example.com', 'service_name' => 'TunSvc',
                'remark_template' => '{brand}-{server}-gRPC', 'sort' => 3,
            ]],
        ];

        foreach ($inbounds as [$tag, $attributes]) {
            Inbound::updateOrCreate(
                ['server_id' => $server->id, 'tag' => $tag],
                $attributes + ['is_active' => true],
            );
        }

        $user = User::firstOrCreate(
            ['email' => 'customer@panel.local'],
            ['name' => 'مشتری نمونه', 'password' => 'password', 'email_verified_at' => now()],
        );

        $plan = Plan::where('slug', 'monthly-60')->firstOrFail();
        $plan->servers()->syncWithoutDetaching([$server->id]);

        $subscription = $user->subscriptions()->first()
            ?? app(SubscriptionService::class)->create($user, $plan);

        $subscription->load('servers.inbounds');

        $this->command->info('کاربر نمونه: customer@panel.local / password');
        $this->command->info('لینک اشتراک: '.route('sub', $subscription->token));
        $this->command->newLine();

        foreach (app(LinkBuilder::class)->forSubscription($subscription) as $link) {
            $this->command->line($link);
            $this->command->newLine();
        }
    }
}
