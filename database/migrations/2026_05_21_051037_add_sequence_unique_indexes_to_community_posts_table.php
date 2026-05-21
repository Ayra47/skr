<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            CREATE UNIQUE INDEX community_posts_community_seq_unique
            ON community_posts (community_id, community_seq)
        ');

        DB::statement('
            CREATE UNIQUE INDEX community_posts_topic_seq_unique
            ON community_posts (topic_id, topic_seq)
            WHERE topic_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS community_posts_topic_seq_unique');
        DB::statement('DROP INDEX IF EXISTS community_posts_community_seq_unique');
    }
};
