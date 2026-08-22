<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_lists_active_plans_only(): void
    {
        Plan::create([
            'name' => 'پلن فعال', 'slug' => 'active', 'duration_days' => 30,
            'traffic_gb' => 50, 'device_limit' => 2, 'price' => 100000, 'is_active' => true,
        ]);

        Plan::create([
            'name' => 'پلن غیرفعال', 'slug' => 'hidden', 'duration_days' => 30,
            'traffic_gb' => 50, 'device_limit' => 2, 'price' => 100000, 'is_active' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('پلن فعال')
            ->assertDontSee('پلن غیرفعال');
    }

    public function test_logged_in_users_are_sent_to_their_dashboard(): void
    {
        $customer = User::create(['name' => 'C', 'email' => 'c@t.local', 'password' => 'secret123']);
        $admin = User::create([
            'name' => 'A', 'email' => 'a@t.local', 'password' => 'secret123', 'is_admin' => true,
        ]);

        $this->actingAs($customer)->get('/')->assertRedirect(route('dashboard'));
        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }
}
