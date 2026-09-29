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
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            // 'type' is the game (currently only 'ayo') so this one table can
            // hold every hot-seat game as more get added, each with its own
            // 'state' shape written and read only by that game's own class.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('state');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
