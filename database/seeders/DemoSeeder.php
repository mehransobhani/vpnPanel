<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\Xray\LinkBuilder;
use Illuminate\Database\Seeder;

/**
 * یک مشتری نمونه با یک سرویس فعال می‌سازد تا خروجی پنل را ببینید.
 *
 * نود واقعی را `panel:setup-local-node` می‌سازد؛ این سیدر سرور نمی‌سازد.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'customer@panel.local'],
            ['name' => 'مشتری نمونه', 'password' => 'password', 'email_verified_at' => now()],
        );

        $plan = Plan::where('slug', 'monthly-60')->firstOrFail();

        $subscription = $user->subscriptions()->first()
            ?? app(SubscriptionService::class)->create($user, $plan);

        $this->command->info('کاربر نمونه: customer@panel.local / password');
        $this->command->info('لینک اشتراک: '.route('sub', $subscription->token));

        if (! Server::node()) {
            $this->command->newLine();
            $this->command->warn('هنوز نودی راه‌اندازی نشده، پس لینک بالا خالی است. اجرا کنید:');
            $this->command->line('  php artisan panel:setup-local-node --address=IP_سرور --port=443');

            return;
        }

        $this->command->newLine();

        foreach (app(LinkBuilder::class)->forSubscription($subscription->fresh()) as $link) {
            $this->command->line($link);
            $this->command->newLine();
        }
    }
}
