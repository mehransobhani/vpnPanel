<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@panel.local'],
            [
                'name' => 'مدیر سیستم',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $plans = [
            ['یک‌ماهه ۳۰ گیگ', 'monthly-30', 30, 30, 2, 120000, 0, false],
            ['یک‌ماهه ۶۰ گیگ', 'monthly-60', 30, 60, 3, 200000, 1, true],
            ['سه‌ماهه ۱۵۰ گیگ', 'quarterly-150', 90, 150, 3, 480000, 2, false],
            ['شش‌ماهه نامحدود', 'semi-unlimited', 180, 0, 5, 1200000, 3, false],
        ];

        foreach ($plans as [$name, $slug, $days, $gb, $devices, $price, $sort, $featured]) {
            Plan::firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $gb
                    ? "$gb گیگابایت ترافیک برای $days روز، تا $devices دستگاه هم‌زمان."
                    : "ترافیک نامحدود برای $days روز، تا $devices دستگاه هم‌زمان.",
                'duration_days' => $days,
                'traffic_gb' => $gb,
                'device_limit' => $devices,
                'price' => $price,
                'sort' => $sort,
                'is_featured' => $featured,
                'is_active' => true,
            ]);
        }
    }
}
