<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_topic_user_state', function (Blueprint $table) {
            $table->id();

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->uuid('topic_id');
            $table->foreign('topic_id')->references('id')->on('community_topics')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->boolean('muted')->default(false);
            $table->boolean('notifications_enabled')->default(true);
            $table->unsignedInteger('unread_count')->default(0);

            // UUID of the last community_post the user has read in this topic
            $table->uuid('last_read_post_id')->nullable();

            $table->timestamps();

            $table->unique(['community_id', 'topic_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_topic_user_state');
    }
};
