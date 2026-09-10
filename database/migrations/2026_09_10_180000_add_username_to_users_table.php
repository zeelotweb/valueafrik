<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        // Backfill existing accounts — the User model generates one going
        // forward (see User::booted()), but rows that predate this column
        // need it filled in once, here, with the same collision-avoidance
        // logic.
        $taken = [];

        DB::table('users')->orderBy('id')->select('id', 'name')->each(function ($user) use (&$taken) {
            $base = Str::slug($user->name) ?: 'user';
            $username = $base;
            $suffix = 1;

            while (in_array($username, $taken, true) || DB::table('users')->where('username', $username)->exists()) {
                $suffix++;
                $username = "{$base}-{$suffix}";
            }

            $taken[] = $username;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });

        // Left nullable at the schema level (changing it to NOT NULL needs
        // doctrine/dbal, which this app doesn't otherwise depend on) — every
        // creation path is guaranteed to populate it via User::booted().
        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
