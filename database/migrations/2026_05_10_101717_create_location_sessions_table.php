<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_minutes'); // 0 = one-time
            $table->text('last_encrypted_payload')->nullable(); // encrypted {lat,lng,accuracy}
            $table->timestamp('expires_at')->nullable(); // null for one-time
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_sessions');
    }
};
