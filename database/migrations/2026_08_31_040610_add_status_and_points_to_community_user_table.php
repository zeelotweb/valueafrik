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
        Schema::table('community_user', function (Blueprint $table) {
            $table->string('status')->default('active')->after('role');
            $table->unsignedInteger('points')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_user', function (Blueprint $table) {
            $table->dropColumn(['status', 'points']);
        });
    }
};
