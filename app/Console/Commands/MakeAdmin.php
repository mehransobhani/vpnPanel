<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdmin extends Command
{
    protected $signature = 'panel:admin {email} {--name=} {--password=}';

    protected $description = 'ساخت کاربر مدیر یا ارتقای کاربر موجود به مدیر';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['is_admin' => true]);

            if ($password = $this->option('password')) {
                $user->update(['password' => Hash::make($password)]);
            }

            $this->info("کاربر {$email} مدیر شد.");

            return self::SUCCESS;
        }

        $password = $this->option('password') ?: str()->random(12);

        User::create([
            'name' => $this->option('name') ?: 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->info("مدیر ساخته شد: $email");
        $this->warn("رمز عبور: $password");

        return self::SUCCESS;
    }
}
