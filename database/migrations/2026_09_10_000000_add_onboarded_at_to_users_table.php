<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarded_at')->nullable()->after('email_verified_at');
        });

        // Existing accounts predate the onboarding flow — treat them as
        // already onboarded so this migration doesn't force every current
        // user through it on their next login.
        DB::table('users')->whereNull('onboarded_at')->update(['onboarded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarded_at');
        });
    }
};
