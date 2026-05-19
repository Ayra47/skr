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
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_post_id')->unique()->constrained('feed_posts')->cascadeOnDelete();
            $table->enum('mode', ['single', 'multiple'])->default('single');
            $table->tinyInteger('max_choices')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->char('secret', 64)->default('');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
