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
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('bookmarkable');
            $table->text('snapshot_body')->nullable();
            $table->unsignedBigInteger('snapshot_author_id')->nullable();
            $table->string('snapshot_author_name')->nullable();
            $table->boolean('snapshot_is_whisper')->default(false);
            $table->timestamp('snapshot_posted_at');
            $table->string('source_label')->nullable();
            $table->boolean('original_deleted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'bookmarkable_type', 'bookmarkable_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
