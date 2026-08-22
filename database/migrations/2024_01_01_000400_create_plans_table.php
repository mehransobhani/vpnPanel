<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_days');
            $table->unsignedInteger('traffic_gb')->default(0);  // 0 = نامحدود
            $table->unsignedSmallInteger('device_limit')->default(1);
            $table->unsignedBigInteger('price');                // به کوچک‌ترین واحد پول
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_server', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unique(['plan_id', 'server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_server');
        Schema::dropIfExists('plans');
    }
};
