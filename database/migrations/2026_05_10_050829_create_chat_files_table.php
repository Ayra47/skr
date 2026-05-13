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
        Schema::create('chat_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('size_encrypted');
            $table->string('storage_path');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('expires_at');
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_files');
    }
};
