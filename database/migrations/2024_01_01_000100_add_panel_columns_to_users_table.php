<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_admin');
            $table->unsignedBigInteger('balance')->default(0)->after('is_active');
            $table->string('phone', 32)->nullable()->after('balance');
            $table->string('telegram_id', 64)->nullable()->index()->after('phone');
            $table->string('referral_code', 16)->nullable()->unique()->after('telegram_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin', 'is_active', 'balance', 'phone', 'telegram_id', 'referral_code',
            ]);
        });
    }
};
