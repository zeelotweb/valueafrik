<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the server cron to actually call `schedule:run` every minute —
// see Forge's "Scheduler" toggle for this site.
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('backup:monitor')->daily()->at('03:00');

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
