<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            // Additive key supporting both bigint (feed_post) and UUID (community_post)
            $table->string('bookmarkable_key', 64)->nullable();
            // UUID of the community, for membership checks on community_post bookmarks
            $table->uuid('community_id')->nullable();
            // Set true async when user leaves a private community
            $table->boolean('access_revoked')->default(false);
        });

        // Backfill: existing feed_post bookmarks use bookmarkable_id as their key
        // ::text is PostgreSQL syntax; CAST() works on both drivers
        DB::statement('UPDATE bookmarks SET bookmarkable_key = CAST(bookmarkable_id AS TEXT) WHERE bookmarkable_key IS NULL');

        // New unique constraint on (user_id, bookmarkable_type, bookmarkable_key)
        DB::statement('
            CREATE UNIQUE INDEX bookmarks_user_type_key_unique
              ON bookmarks (user_id, bookmarkable_type, bookmarkable_key)
        ');

        DB::statement('
            CREATE INDEX bookmarks_type_key_idx
              ON bookmarks (bookmarkable_type, bookmarkable_key)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bookmarks_user_type_key_unique');
        DB::statement('DROP INDEX IF EXISTS bookmarks_type_key_idx');

        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropColumn(['bookmarkable_key', 'community_id', 'access_revoked']);
        });
    }
};
