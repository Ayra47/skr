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
        Schema::create('profile_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('show_shared_chats')->default(true);
            $table->boolean('show_shared_groups')->default(true);
            $table->string('profile_access', 12)->default('everyone');
            $table->string('online_status_visibility', 12)->default('everyone');
            $table->string('shared_friends_count_visibility', 12)->default('everyone');
            $table->string('feed_posts_count_visibility', 12)->default('everyone');
            $table->string('profile_posts_visibility', 12)->default('everyone');
            $table->string('avatar_visibility', 12)->default('everyone');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_settings');
    }
};
