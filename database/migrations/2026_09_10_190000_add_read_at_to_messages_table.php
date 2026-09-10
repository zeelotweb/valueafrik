<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every conversation is strictly 1:1 (Conversation::between()), so
     * "read by whom" is never ambiguous — a single nullable timestamp per
     * message (read by the one other participant) is enough; no per-
     * recipient pivot table needed the way group messaging would require.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
