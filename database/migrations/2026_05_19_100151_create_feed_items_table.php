<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            // nullable: feed_posts.user_id = NULL for whisper posts
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();

            $table->string('item_type', 50);
            $table->string('source_type', 50);
            $table->string('source_id', 64); // bigint-as-string OR UUID

            $table->uuid('community_id')->nullable();
            $table->uuid('topic_id')->nullable();
            $table->uuid('post_id')->nullable();

            $table->string('visibility_scope', 30)->default('public');

            $table->boolean('show_in_feed')->default(true);
            $table->boolean('show_in_profile_activity')->default(true);

            $table->timestamp('sort_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->timestamp('deleted_at', 0)->nullable();
        });

        // CHECK constraints are PostgreSQL-only; SQLite in-memory tests skip them
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE feed_items
                  ADD CONSTRAINT feed_items_item_type_check CHECK (item_type IN (
                    'feed_post_created','community_post_created','community_created',
                    'community_joined','community_role_changed','community_topic_created'
                  )),
                  ADD CONSTRAINT feed_items_source_type_check CHECK (source_type IN (
                    'feed_post','community_post','community','community_topic','community_member'
                  )),
                  ADD CONSTRAINT feed_items_visibility_scope_check CHECK (visibility_scope IN (
                    'public','friends','community_members_only','private'
                  ))
            ");
        }

        // Hot path: global feed
        DB::statement('
            CREATE INDEX feed_items_feed_idx
              ON feed_items (show_in_feed, sort_at DESC, id DESC)
              WHERE deleted_at IS NULL
        ');

        // Profile activity
        DB::statement('
            CREATE INDEX feed_items_profile_activity_idx
              ON feed_items (actor_id, show_in_profile_activity, sort_at DESC, id DESC)
              WHERE deleted_at IS NULL
        ');

        // Idempotency: prevent duplicate projections
        DB::statement('
            CREATE UNIQUE INDEX feed_items_source_unique
              ON feed_items (source_type, source_id, item_type)
        ');

        // Cleanup by community
        DB::statement('
            CREATE INDEX feed_items_community_idx
              ON feed_items (community_id, sort_at DESC, id DESC)
              WHERE deleted_at IS NULL
        ');

        // Cleanup by post
        DB::statement('
            CREATE INDEX feed_items_post_idx
              ON feed_items (post_id)
              WHERE post_id IS NOT NULL
        ');

        // Filter by item type
        DB::statement('
            CREATE INDEX feed_items_type_idx
              ON feed_items (item_type, sort_at DESC, id DESC)
              WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};
