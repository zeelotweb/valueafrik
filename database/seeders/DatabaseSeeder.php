<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reference data every environment needs, production included.
        // Safe to run with `composer install --no-dev` since it never
        // touches model factories / the fake() helper.
        $this->call([
            LanguageSeeder::class,
            HeritageSeeder::class,
            InterestSeeder::class,
        ]);

        // Local/testing convenience data only — factories require
        // fakerphp/faker, which is deliberately dev-only and won't be
        // installed in production.
        if (! app()->isProduction()) {
            // User::factory(10)->create();

            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
