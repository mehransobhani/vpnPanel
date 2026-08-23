<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پنل تک‌نودی می‌شود: فقط سروری که خودِ اپ روی آن اجرا می‌شود کانفیگ می‌سازد.
 *
 * ستون‌های SSH، انتخاب درایور و ظرفیت بی‌معنی می‌شوند و همراه با جدول
 * انتخاب سرور برای هر پلن حذف می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('plan_server');

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'sync_driver', 'ssh_host', 'ssh_port', 'ssh_user',
                'ssh_password', 'ssh_private_key', 'capacity',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('sync_driver', 16)->default('local')->after('address');
            $table->string('ssh_host')->nullable()->after('sync_driver');
            $table->unsignedSmallInteger('ssh_port')->default(22)->after('ssh_host');
            $table->string('ssh_user')->default('root')->after('ssh_port');
            $table->text('ssh_password')->nullable()->after('ssh_user');
            $table->text('ssh_private_key')->nullable()->after('ssh_password');
            $table->unsignedInteger('capacity')->default(0)->after('xray_config_path');
        });

        Schema::create('plan_server', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unique(['plan_id', 'server_id']);
        });
    }
};
