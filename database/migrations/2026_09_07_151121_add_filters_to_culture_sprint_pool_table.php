<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('culture_sprint_pool', function (Blueprint $table) {
            // The DMV-style "line" someone is waiting in: a required topic
            // (matched exactly — two people only pair up over the same
            // topic) and an optional region — a courtesy between the two
            // forms only, never checked against declared heritage. See
            // CultureSprintPool::matchingQuery().
            $table->string('topic')->nullable()->after('user_id');
            $table->string('region')->nullable()->after('topic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('culture_sprint_pool', function (Blueprint $table) {
            $table->dropColumn(['topic', 'region']);
        });
    }
};
