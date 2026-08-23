<?php

namespace Tests\Feature;

use App\Models\Inbound;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Xray\LinkBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(): Subscription
    {
        $user = User::create(['name' => 'T', 'email' => 't@t.local', 'password' => 'secret123']);

        return Subscription::create([
            'user_id' => $user->id,
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'password' => 'trojanpass',
            'token' => str_repeat('a', 32),
            'remark' => 'Test',
            'email_tag' => 't-abc@panel',
            'traffic_limit' => 0,
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function inbound(array $attributes): Inbound
    {
        $server = Server::create([
            'name' => 'Node', 'address' => 'node.example.com', 'country' => 'de',
        ]);

        return $server->inbounds()->create($attributes + [
            'tag' => 'in', 'port' => 443, 'remark_template' => '{brand}-{country}',
        ]);
    }

    public function test_vless_reality_link_carries_every_required_parameter(): void
    {
        $link = app(LinkBuilder::class)->build(
            $this->inbound([
                'protocol' => 'vless', 'network' => 'tcp', 'security' => 'reality',
                'sni' => 'www.microsoft.com', 'fingerprint' => 'chrome',
                'flow' => 'xtls-rprx-vision', 'reality_public_key' => 'PBK',
                'reality_short_id' => 'SID', 'reality_spider_x' => '/',
            ]),
            $this->subscription(),
        );

        $this->assertStringStartsWith('vless://11111111-2222-3333-4444-555555555555@node.example.com:443?', $link);

        foreach (['encryption=none', 'type=tcp', 'security=reality', 'sni=www.microsoft.com',
            'fp=chrome', 'pbk=PBK', 'sid=SID', 'spx=%2F', 'flow=xtls-rprx-vision'] as $needle) {
            $this->assertStringContainsString($needle, $link);
        }

        $this->assertStringEndsWith('#MyVPN-DE', $link);
    }

    public function test_flow_is_dropped_when_transport_is_not_tcp(): void
    {
        $link = app(LinkBuilder::class)->build(
            $this->inbound([
                'protocol' => 'vless', 'network' => 'ws', 'security' => 'tls',
                'flow' => 'xtls-rprx-vision', 'path' => '/ray', 'host' => 'cdn.example.com',
            ]),
            $this->subscription(),
        );

        $this->assertStringNotContainsString('flow=', $link);
        $this->assertStringContainsString('path=%2Fray', $link);
        $this->assertStringContainsString('host=cdn.example.com', $link);
    }

    public function test_vmess_payload_is_valid_base64_json(): void
    {
        $link = app(LinkBuilder::class)->build(
            $this->inbound([
                'protocol' => 'vmess', 'network' => 'ws', 'security' => 'tls',
                'path' => '/ray', 'host' => 'cdn.example.com', 'sni' => 'cdn.example.com',
            ]),
            $this->subscription(),
        );

        $payload = json_decode(base64_decode(substr($link, strlen('vmess://'))), true);

        $this->assertIsArray($payload);
        $this->assertSame('2', $payload['v']);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $payload['id']);
        $this->assertSame('ws', $payload['net']);
        $this->assertSame('/ray', $payload['path']);
        $this->assertSame('tls', $payload['tls']);
    }

    public function test_trojan_link_uses_password_not_uuid(): void
    {
        $link = app(LinkBuilder::class)->build(
            $this->inbound([
                'protocol' => 'trojan', 'network' => 'grpc', 'security' => 'tls',
                'service_name' => 'TunSvc', 'sni' => 'grpc.example.com',
            ]),
            $this->subscription(),
        );

        $this->assertStringStartsWith('trojan://trojanpass@node.example.com:443?', $link);
        $this->assertStringContainsString('serviceName=TunSvc', $link);
        $this->assertStringNotContainsString('11111111-2222', $link);
    }

    public function test_ipv6_address_is_bracketed(): void
    {
        $inbound = $this->inbound(['protocol' => 'vless', 'network' => 'tcp', 'security' => 'none']);
        $inbound->server->update(['address' => '2001:db8::1']);

        $link = app(LinkBuilder::class)->build($inbound->fresh(), $this->subscription());

        $this->assertStringContainsString('@[2001:db8::1]:443?', $link);
    }
}
