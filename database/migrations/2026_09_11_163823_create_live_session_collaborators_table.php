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
        Schema::create('live_session_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('requested_at');
            // Null until the host approves — a row can exist purely as a
            // pending request, removed on deny rather than marked denied,
            // since a denied request carries nothing worth keeping around.
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['live_session_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_session_collaborators');
    }
};
