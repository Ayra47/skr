<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_member_keys', function (Blueprint $table) {
            $table->id();

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->uuid('epoch_id');
            $table->foreign('epoch_id')->references('id')->on('community_key_epochs')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Symmetric community key encrypted with this member's public key
            $table->text('encrypted_key');

            $table->timestamp('created_at', 0)->useCurrent();

            $table->unique(['community_id', 'epoch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_member_keys');
    }
};
