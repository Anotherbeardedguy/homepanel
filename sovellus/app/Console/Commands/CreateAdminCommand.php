<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminCommand extends Command
{
    protected $signature = 'homepanel:create-admin {username} {--name=Ylläpitäjä}';

    protected $description = 'Create an administrator. The password is requested without echo.';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $password = (string) $this->secret('Salasana');
        $confirmation = (string) $this->secret('Salasana uudelleen');

        if ($password === '' || $password !== $confirmation) {
            $this->error('Salasanat eivät täsmää.');

            return self::FAILURE;
        }

        if (User::query()->where('username', $username)->exists()) {
            $this->error('Kirjautumistunnus on jo käytössä.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => (string) $this->option('name'),
            'username' => $username,
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        $this->info('Ylläpitäjä luotu.');

        return self::SUCCESS;
    }
}
