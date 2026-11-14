<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Grant or remove admin from the command line (e.g. to recover access). */
class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email : The account’s email} {--remove : Remove admin instead}';

    protected $description = 'Make a user an admin (or remove admin with --remove)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        $admin = ! $this->option('remove');
        if (! $admin && User::query()->where('is_admin', true)->count() <= 1 && $user->isAdmin()) {
            $this->error('The site needs at least one admin.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => $admin])->save();
        $this->info($admin ? "{$user->email} is now an admin." : "{$user->email} is no longer an admin.");

        return self::SUCCESS;
    }
}
