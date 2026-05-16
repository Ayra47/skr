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
        Schema::create('feed_comment_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_comment_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('created_at');

            $table->index(['feed_comment_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_comment_edits');
    }
};
