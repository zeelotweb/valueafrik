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
        Schema::create('post_hides', function (Blueprint $table) {
            $table->id();
            // "Hide for me" is per-viewer, not per-post — same reasoning as
            // message_hides. Polymorphic so WallPost and CommunityPost (and
            // anything else that adopts HasHides later) share one table
            // instead of a copy each.
            $table->morphs('hideable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['hideable_type', 'hideable_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_hides');
    }
};
