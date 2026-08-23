<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubscription(int $trafficGb = 10): Subscription
    {
        Queue::fake(); // بدون تلاش برای اتصال به نود

        $server = Server::create([
            'name' => 'Node', 'address' => 'node.example.com', 'country' => 'de',
        ]);

        $server->inbounds()->create([
            'tag' => 'in', 'protocol' => 'vless', 'port' => 443,
            'network' => 'tcp', 'security' => 'reality',
            'sni' => 'www.microsoft.com', 'reality_public_key' => 'PBK',
            'remark_template' => '{brand}-{country}',
        ]);

        $plan = Plan::create([
            'name' => 'P', 'slug' => 'p', 'duration_days' => 30,
            'traffic_gb' => $trafficGb, 'device_limit' => 2, 'price' => 1000,
        ]);

        $user = User::create(['name' => 'T', 'email' => 't@t.local', 'password' => 'secret123']);

        return app(SubscriptionService::class)->create($user, $plan);
    }

    public function test_endpoint_returns_base64_list_and_usage_headers(): void
    {
        $subscription = $this->makeSubscription();

        $response = $this->get(route('sub', $subscription->token));

        $response->assertOk();
        $response->assertHeader('Profile-Update-Interval', '12');
        $response->assertHeader(
            'Subscription-Userinfo',
            sprintf('upload=0; download=0; total=%d; expire=%d',
                10 * 1024 ** 3, $subscription->expires_at->timestamp),
        );

        $this->assertStringStartsWith('vless://', base64_decode($response->getContent()));
    }

    public function test_clash_format_is_served_to_clash_clients(): void
    {
        $subscription = $this->makeSubscription();

        $response = $this->withHeaders(['User-Agent' => 'clash-verge/1.6'])
            ->get(route('sub', $subscription->token));

        $response->assertOk();
        $this->assertStringContainsString('proxies:', $response->getContent());
        $this->assertStringContainsString('reality-opts:', $response->getContent());
    }

    public function test_expired_subscription_returns_empty_body(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->update(['status' => Subscription::EXPIRED]);

        $this->get(route('sub', $subscription->token))
            ->assertOk()
            ->assertSee('', false);

        $this->assertSame('', $this->get(route('sub', $subscription->token))->getContent());
    }

    public function test_exhausted_traffic_returns_empty_body(): void
    {
        $subscription = $this->makeSubscription(1);
        $subscription->update(['download' => 2 * 1024 ** 3]);

        $this->assertSame('', $this->get(route('sub', $subscription->token))->getContent());
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get(route('sub', 'nope'))->assertNotFound();
    }
}
