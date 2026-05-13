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
        Schema::create('friend_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('friend_code_id')->nullable()->constrained('friend_codes')->onDelete('set null');
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->unique(['sender_id', 'receiver_id', 'status'], 'unique_pending_request');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friend_requests');
    }
};
