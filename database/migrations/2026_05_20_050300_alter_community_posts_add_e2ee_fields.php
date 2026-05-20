<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            // E2EE content replaces plaintext body
            $table->text('ciphertext')->nullable()->after('epoch_id');
            $table->string('nonce', 255)->nullable()->after('ciphertext');

            // Ordering and sequencing
            $table->unsignedBigInteger('community_seq')->default(0)->after('nonce');
            $table->unsignedBigInteger('topic_seq')->default(0)->after('community_seq');

            // Lifecycle
            $table->unsignedInteger('ttl_seconds')->nullable()->after('topic_seq');
            $table->string('moderation_status', 30)->default('visible')->after('ttl_seconds');

            // Engagement denormalization
            $table->unsignedInteger('reply_count')->default(0)->after('comment_count');
            $table->unsignedInteger('attachments_count')->default(0)->after('reply_count');

            // Idempotency
            $table->string('client_idempotency_key', 100)->nullable()->after('attachments_count');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE community_posts
                  ADD CONSTRAINT community_posts_ttl_seconds_check
                    CHECK (ttl_seconds IS NULL OR ttl_seconds > 0),
                  ADD CONSTRAINT community_posts_moderation_status_check
                    CHECK (moderation_status IN ('visible','hidden','deleted_by_moderator'))
            ");

            DB::statement('CREATE INDEX community_posts_community_seq_idx ON community_posts (community_id, community_seq DESC) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX community_posts_topic_seq_idx ON community_posts (topic_id, topic_seq DESC) WHERE deleted_at IS NULL AND topic_id IS NOT NULL');
            DB::statement('CREATE INDEX community_posts_expires_idx ON community_posts (expires_at) WHERE expires_at IS NOT NULL AND deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX community_posts_idempotency_unique ON community_posts (user_id, client_idempotency_key) WHERE client_idempotency_key IS NOT NULL');
        }

        // Drop plaintext body — E2EE communities must not store plaintext
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn('body');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_posts DROP CONSTRAINT IF EXISTS community_posts_ttl_seconds_check');
            DB::statement('ALTER TABLE community_posts DROP CONSTRAINT IF EXISTS community_posts_moderation_status_check');
            DB::statement('DROP INDEX IF EXISTS community_posts_community_seq_idx');
            DB::statement('DROP INDEX IF EXISTS community_posts_topic_seq_idx');
            DB::statement('DROP INDEX IF EXISTS community_posts_expires_idx');
            DB::statement('DROP INDEX IF EXISTS community_posts_idempotency_unique');
        }

        Schema::table('community_posts', function (Blueprint $table) {
            $table->text('body')->nullable()->after('epoch_id');
            $table->dropColumn([
                'ciphertext', 'nonce', 'community_seq', 'topic_seq',
                'ttl_seconds', 'moderation_status', 'reply_count',
                'attachments_count', 'client_idempotency_key',
            ]);
        });
    }
};
