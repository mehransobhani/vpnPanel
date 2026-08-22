<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country', 2)->nullable();
            // آدرسی که کاربر نهایی به آن وصل می‌شود (دامنه یا IP)
            $table->string('address');
            $table->string('sync_driver', 16)->default('ssh'); // ssh | manual
            $table->string('ssh_host')->nullable();
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('ssh_user')->default('root');
            $table->text('ssh_password')->nullable();     // encrypted cast
            $table->text('ssh_private_key')->nullable();  // encrypted cast
            $table->string('xray_bin')->default('/usr/local/bin/xray');
            $table->string('xray_api')->default('127.0.0.1:10085');
            $table->string('xray_config_path')->default('/usr/local/etc/xray/config.json');
            $table->unsignedInteger('capacity')->default(0); // 0 = نامحدود
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_error')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
