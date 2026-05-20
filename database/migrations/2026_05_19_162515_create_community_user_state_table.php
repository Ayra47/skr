<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_user_state', function (Blueprint $table) {
            $table->id();

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('muted')->default(false);
            $table->unsignedInteger('unread_posts_count')->default(0);
            $table->timestamp('last_visited_at', 0)->nullable();

            $table->timestamps();

            $table->unique(['community_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_user_state');
    }
};
