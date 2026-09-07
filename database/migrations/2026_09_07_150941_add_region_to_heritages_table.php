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
        Schema::table('heritages', function (Blueprint $table) {
            // The broad world-region a heritage belongs to (Africa, Asia,
            // Middle East, Europe, Americas, Oceania) — the same grouping
            // HeritageSeeder already organizes its list into. Powers the
            // Culture Sprint "region I'm curious about" filter; existing
            // rows are backfilled by re-running that seeder.
            $table->string('region')->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('heritages', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
