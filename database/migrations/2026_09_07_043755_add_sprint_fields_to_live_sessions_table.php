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
        Schema::table('live_sessions', function (Blueprint $table) {
            // Culture Sprints are matched, not called — neither side has
            // already consented the way a caller has, so both host and
            // callee independently accept before the room goes live.
            $table->timestamp('host_accepted_at')->nullable()->after('answered_at');
            $table->timestamp('callee_accepted_at')->nullable()->after('host_accepted_at');
            $table->string('culture_word')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn(['host_accepted_at', 'callee_accepted_at', 'culture_word']);
        });
    }
};
