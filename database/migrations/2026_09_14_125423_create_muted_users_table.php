<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muted_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('muter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('muted_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['muter_id', 'muted_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muted_users');
    }
};
