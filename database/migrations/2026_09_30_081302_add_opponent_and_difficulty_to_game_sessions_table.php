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
        Schema::table('game_sessions', function (Blueprint $table) {
            // 'human' (today's hot-seat) or 'computer'; 'online' (a real
            // second account) is reserved for when real multiplayer lands.
            $table->string('opponent')->default('human')->after('type');
            // Only meaningful when opponent is 'computer'.
            $table->string('difficulty')->nullable()->after('opponent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn(['opponent', 'difficulty']);
        });
    }
};
