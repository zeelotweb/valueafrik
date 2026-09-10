<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('hashtag_taggable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hashtag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamps();

            $table->unique(['hashtag_id', 'taggable_type', 'taggable_id'], 'hashtag_taggable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hashtag_taggable');
        Schema::dropIfExists('hashtags');
    }
};
