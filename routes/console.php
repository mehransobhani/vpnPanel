<?php

use Illuminate\Support\Facades\Schedule;

// خواندن مصرف از نودها
Schedule::command('panel:sync-usage')->everyFiveMinutes()->withoutOverlapping();

// منقضی کردن سرویس‌های تمام‌شده
Schedule::command('panel:expire')->hourly()->withoutOverlapping();

// کاربران Xray در حافظه‌اند؛ اگر نودی ری‌استارت شده باشد دوباره نوشته می‌شوند.
Schedule::command('panel:heal-nodes')->everyTenMinutes()->withoutOverlapping();

// پاکسازی لاگ‌های قدیمی ترافیک (بیش از ۹۰ روز)
Schedule::call(function () {
    \App\Models\TrafficLog::where('date', '<', now()->subDays(90))->delete();
})->dailyAt('03:30')->name('prune-traffic-logs');

Schedule::command('queue:prune-failed --hours=168')->daily();
