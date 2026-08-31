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
        Schema::create('bridge_posts', function (Blueprint $table) {
            $table->id();
            $table->string('theme');
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('initiator_body')->nullable();
            $table->text('partner_body')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index(['initiator_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bridge_posts');
    }
};
