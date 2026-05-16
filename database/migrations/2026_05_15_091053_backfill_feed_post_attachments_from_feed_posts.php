<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('feed_posts')
            ->whereNotNull('attachment_path')
            ->orderBy('id')
            ->chunkById(100, function ($posts): void {
                foreach ($posts as $post) {
                    DB::table('feed_post_attachments')->insert([
                        'feed_post_id' => $post->id,
                        'path' => $post->attachment_path,
                        'name' => $post->attachment_name,
                        'mime' => $post->attachment_mime,
                        'size' => $post->attachment_size,
                        'position' => 1,
                        'created_at' => $post->created_at,
                        'updated_at' => $post->updated_at,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('feed_posts')
            ->whereNotNull('attachment_path')
            ->orderBy('id')
            ->chunkById(100, function ($posts): void {
                foreach ($posts as $post) {
                    DB::table('feed_post_attachments')
                        ->where('feed_post_id', $post->id)
                        ->where('path', $post->attachment_path)
                        ->where('position', 1)
                        ->delete();
                }
            });
    }
};
