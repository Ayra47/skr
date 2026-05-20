<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_invite_uses', function (Blueprint $table) {
            $table->id();

            $table->uuid('invite_id');
            $table->foreign('invite_id')->references('id')->on('community_invites')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('used_at', 0)->useCurrent();

            $table->unique(['invite_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_invite_uses');
    }
};
