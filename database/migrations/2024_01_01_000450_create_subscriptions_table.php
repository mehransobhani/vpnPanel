<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();             // شناسه کاربر در Xray (vless/vmess)
            $table->string('password', 64);             // رمز trojan
            $table->string('token', 48)->unique();      // توکن لینک اشتراک
            $table->string('remark');                   // نام نمایشی سرویس
            $table->string('email_tag')->unique();      // email در Xray = کلید آمار مصرف
            $table->string('status', 16)->default('active'); // active | expired | exhausted | disabled
            $table->unsignedBigInteger('traffic_limit')->default(0); // بایت، 0 = نامحدود
            $table->unsignedBigInteger('upload')->default(0);
            $table->unsignedBigInteger('download')->default(0);
            $table->unsignedSmallInteger('device_limit')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_online_at')->nullable();
            $table->unsignedInteger('reset_count')->default(0);
            $table->boolean('auto_renew')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index('user_id');
        });

        // سرورهایی که این سرویس روی آن‌ها فعال است
        Schema::create('server_subscription', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('state', 16)->default('pending'); // pending | synced | failed | removed
            $table->string('message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_subscription');
        Schema::dropIfExists('subscriptions');
    }
};
