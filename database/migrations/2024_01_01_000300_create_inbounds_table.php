<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('tag');                       // tag اینباند در config.json نود
            $table->string('protocol', 16);              // vless | vmess | trojan
            $table->unsignedSmallInteger('port');
            $table->string('network', 16)->default('tcp'); // tcp | ws | grpc | http | httpupgrade | xhttp
            $table->string('security', 16)->default('none'); // none | tls | reality
            $table->string('sni')->nullable();
            $table->string('host')->nullable();          // Host header برای ws/http
            $table->string('path')->nullable();          // مسیر ws/xhttp
            $table->string('service_name')->nullable();  // grpc serviceName
            $table->string('header_type', 16)->default('none');
            $table->string('flow', 32)->nullable();      // xtls-rprx-vision
            $table->string('fingerprint', 16)->nullable(); // chrome, firefox, ...
            $table->string('reality_public_key')->nullable();
            $table->string('reality_short_id', 32)->nullable();
            $table->string('reality_spider_x')->nullable();
            $table->string('alpn')->nullable();
            $table->boolean('allow_insecure')->default(false);
            // مثال: {brand}-{country}-{plan}
            $table->string('remark_template')->default('{brand}-{server}');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['server_id', 'tag']);
            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbounds');
    }
};
