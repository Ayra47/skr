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
        Schema::table('feed_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('feed_comments')
                ->cascadeOnDelete();
            $table->timestamp('deleted_at')->nullable()->after('body');

            $table->index(['feed_post_id', 'parent_id', 'created_at']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feed_comments', function (Blueprint $table) {
            $table->dropIndex(['feed_post_id', 'parent_id', 'created_at']);
            $table->dropIndex(['deleted_at']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'deleted_at']);
        });
    }
};
