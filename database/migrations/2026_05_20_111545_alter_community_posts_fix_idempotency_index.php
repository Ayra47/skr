<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Replace the old per-user-only index with a per-(community, user) index so
            // the same idempotency key can be reused across different communities.
            DB::statement('DROP INDEX IF EXISTS community_posts_idempotency_unique');
            DB::statement('
                CREATE UNIQUE INDEX community_posts_idempotency_unique
                ON community_posts (community_id, user_id, client_idempotency_key)
                WHERE client_idempotency_key IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS community_posts_idempotency_unique');
            DB::statement('
                CREATE UNIQUE INDEX community_posts_idempotency_unique
                ON community_posts (user_id, client_idempotency_key)
                WHERE client_idempotency_key IS NOT NULL
            ');
        }
    }
};
