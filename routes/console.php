<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:grant {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user found with email {$email}.");

        return self::FAILURE;
    }

    $user->forceFill(['is_admin' => true])->save();

    $this->info("{$user->name} ({$email}) is now a platform admin.");
})->purpose('Grant platform admin access to a user by email');

Artisan::command('admin:revoke {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user found with email {$email}.");

        return self::FAILURE;
    }

    $user->forceFill(['is_admin' => false])->save();

    $this->info("{$user->name} ({$email}) is no longer a platform admin.");
})->purpose('Revoke platform admin access from a user by email');
