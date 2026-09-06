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
            $table->foreignId('callee_id')->nullable()->after('host_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('answered_at')->nullable()->after('started_at');
            $table->string('ended_reason')->nullable()->after('status');

            $table->index(['callee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropIndex(['callee_id', 'status']);
            $table->dropConstrainedForeignId('callee_id');
            $table->dropColumn(['answered_at', 'ended_reason']);
        });
    }
};
