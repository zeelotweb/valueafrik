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
        Schema::create('message_hides', function (Blueprint $table) {
            $table->id();
            // "Hide for me" is per-viewer, not per-message — either
            // participant in a conversation can hide any message (their own
            // or the other person's) from just their own view, with no
            // effect on what the other side sees. A real table rather than
            // a boolean column, since a boolean can't represent "hidden for
            // A but not B" on the same row.
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_hides');
    }
};
