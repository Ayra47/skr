<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_reactions', function (Blueprint $table) {
            $table->id();

            $table->uuid('post_id');
            $table->foreign('post_id')->references('id')->on('community_posts')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Emoji or short reaction key (e.g. '👍', '❤️', 'like')
            $table->string('emoji', 30);

            $table->timestamp('created_at', 0)->useCurrent();

            $table->unique(['post_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_reactions');
    }
};
