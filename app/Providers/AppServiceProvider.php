<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Locally, `php artisan dev` runs this alongside Octane. In
        // production it needs its own persistent Forge Daemon — real-time
        // messaging, calls, and streams (all dispatched via
        // ShouldBroadcastNow) silently stop working without it.
        DevCommands::register('reverb:start --debug', 'reverb');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Composition rules (mixed case, a number, a symbol) were dropped —
        // NIST 800-63B recommends against them: they push people toward
        // predictable patterns ("Password1!") and mostly just add signup
        // friction, without the real protection uncompromised() already
        // gives by rejecting passwords that show up in known breach data.
        // Length is what actually matters; 10 is a reasonable floor for a
        // consumer platform without demanding a password manager to pass.
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(10)->uncompromised()
            : null,
        );
    }
}
