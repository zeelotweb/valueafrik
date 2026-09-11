<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A small companion derivative for inline display (feed grids, chat
     * bubbles, dashboard cards) — the full-size `path` is reserved for the
     * lightbox. Nullable: rows written before this column existed, and
     * passthrough types (GIF/SVG) that never get a thumbnail, fall back
     * to serving the full image (see Media::thumbnailUrl()).
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('thumbnail_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('thumbnail_path');
        });
    }
};
