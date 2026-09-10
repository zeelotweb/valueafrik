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
        // A dedicated column rather than piggybacking on updated_at — this
        // one only ever means "the author edited the content," so an
        // "edited" indicator in the UI can rely on it without ambiguity.
        Schema::table('wall_posts', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('body');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('body');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wall_posts', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};
