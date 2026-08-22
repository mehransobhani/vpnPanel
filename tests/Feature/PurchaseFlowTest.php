<?php

namespace Tests\Feature;

use App\Jobs\SyncSubscriptionToNode;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private User $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $server = Server::create([
            'name' => 'Node', 'address' => 'node.example.com', 'sync_driver' => 'manual',
        ]);
        $server->inbounds()->create([
            'tag' => 'in', 'protocol' => 'vless', 'port' => 443,
            'network' => 'tcp', 'security' => 'none', 'remark_template' => '{brand}',
        ]);

        $this->plan = Plan::create([
            'name' => 'Monthly', 'slug' => 'monthly', 'duration_days' => 30,
            'traffic_gb' => 50, 'device_limit' => 2, 'price' => 100000,
        ]);
        $this->plan->servers()->attach($server);

        $this->customer = User::create(['name' => 'C', 'email' => 'c@t.local', 'password' => 'secret123']);
        $this->admin = User::create([
            'name' => 'A', 'email' => 'a@t.local', 'password' => 'secret123', 'is_admin' => true,
        ]);
    }

    public function test_manual_order_stays_pending_until_admin_approves(): void
    {
        $this->actingAs($this->customer)
            ->post(route('orders.store'), ['plan' => 'monthly', 'payment_method' => 'manual'])
            ->assertRedirect();

        $order = Order::sole();
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertNull($order->subscription_id);
        $this->assertDatabaseCount('subscriptions', 0);

        $this->actingAs($this->admin)
            ->post(route('admin.orders.approve', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(Order::PAID, $order->status);
        $this->assertNotNull($order->paid_at);

        $subscription = Subscription::sole();
        $this->assertSame($order->subscription_id, $subscription->id);
        $this->assertSame(50 * 1024 ** 3, $subscription->traffic_limit);
        $this->assertSame(30, (int) now()->startOfDay()->diffInDays($subscription->expires_at->startOfDay()));

        Queue::assertPushed(SyncSubscriptionToNode::class);
    }

    public function test_approving_twice_does_not_create_a_second_subscription(): void
    {
        $this->actingAs($this->customer)
            ->post(route('orders.store'), ['plan' => 'monthly', 'payment_method' => 'manual']);

        $order = Order::sole();
        $this->actingAs($this->admin)->post(route('admin.orders.approve', $order));
        $this->actingAs($this->admin)->post(route('admin.orders.approve', $order));

        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_wallet_payment_delivers_immediately_and_debits_balance(): void
    {
        $this->customer->update(['balance' => 150000]);

        $this->actingAs($this->customer)
            ->post(route('orders.store'), ['plan' => 'monthly', 'payment_method' => 'wallet'])
            ->assertRedirect();

        $this->assertSame(Order::PAID, Order::sole()->status);
        $this->assertSame(50000, $this->customer->refresh()->balance);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_wallet_payment_is_refused_when_balance_is_short(): void
    {
        $this->customer->update(['balance' => 500]);

        $this->actingAs($this->customer)
            ->post(route('orders.store'), ['plan' => 'monthly', 'payment_method' => 'wallet'])
            ->assertSessionHasErrors('wallet');

        $this->assertSame(500, $this->customer->refresh()->balance);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_renewal_extends_from_current_expiry_and_keeps_the_same_link(): void
    {
        $this->customer->update(['balance' => 300000]);
        $this->actingAs($this->customer)
            ->post(route('orders.store'), ['plan' => 'monthly', 'payment_method' => 'wallet']);

        $subscription = Subscription::sole();
        $token = $subscription->token;
        $expiry = $subscription->expires_at->copy();
        $subscription->update(['download' => 1024 ** 3]);

        $this->actingAs($this->customer)->post(route('orders.store'), [
            'plan' => 'monthly',
            'payment_method' => 'wallet',
            'subscription' => $token,
        ]);

        $subscription->refresh();
        $this->assertSame($token, $subscription->token, 'لینک اشتراک نباید عوض شود');
        $this->assertSame(0, $subscription->download, 'مصرف باید صفر شود');
        $this->assertSame(
            $expiry->copy()->addDays(30)->toDateString(),
            $subscription->expires_at->toDateString(),
        );
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_customer_cannot_reach_the_admin_area(): void
    {
        $this->actingAs($this->customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_customer_cannot_open_another_users_subscription(): void
    {
        $this->customer->update(['balance' => 100000]);
        $this->actingAs($this->customer)
            ->post(route('orders.store'), ['plan' => 'monthly', 'payment_method' => 'wallet']);

        $intruder = User::create(['name' => 'X', 'email' => 'x@t.local', 'password' => 'secret123']);

        $this->actingAs($intruder)
            ->get(route('subscriptions.show', Subscription::sole()))
            ->assertForbidden();
    }
}
