<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Wall posts have no community to attach a report to — this was always
     * a gap (the admin reports view already defensively checks
     * `@if ($report->community)`), just never reachable until Wall posts
     * grew a report action too.
     */
    public function up(): void
    {
        Schema::table('community_reports', function (Blueprint $table) {
            $table->foreignId('community_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_reports', function (Blueprint $table) {
            $table->foreignId('community_id')->nullable(false)->change();
        });
    }
};
