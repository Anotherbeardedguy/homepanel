<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class EnsureAdminCommand extends Command
{
    protected $signature = 'homepanel:ensure-admin';

    protected $description = 'Create the first administrator from the environment when none exists';

    public function handle(): int
    {
        if (User::query()->where('role', UserRole::Admin)->exists()) {
            $this->info('An administrator already exists.');

            return self::SUCCESS;
        }

        $username = (string) config('homepanel.admin_username');
        $password = (string) config('homepanel.admin_password');

        if ($username === '' || $password === '') {
            $this->error('No administrator exists. Set HOMEPANEL_ADMIN_USERNAME and HOMEPANEL_ADMIN_PASSWORD.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => (string) config('homepanel.admin_name'),
            'username' => $username,
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
