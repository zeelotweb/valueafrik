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
        // culture_sprint_pool.topic backs the exact-match filter that runs
        // on every join/poll of the queue (CultureSprintPool::matchingQuery)
        // and had no index at all.
        Schema::table('culture_sprint_pool', function (Blueprint $table) {
            $table->index('topic');
        });

        // The unread-badge query filters by notifiable + whereNull(read_at)
        // — morphs() already indexes (notifiable_type, notifiable_id), but
        // nothing covered read_at, so that filter fell back to a scan.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('culture_sprint_pool', function (Blueprint $table) {
            $table->dropIndex(['topic']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_read_at_index');
        });
    }
};
