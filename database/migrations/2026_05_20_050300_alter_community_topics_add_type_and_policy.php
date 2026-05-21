<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_topics', function (Blueprint $table) {
            $table->string('type', 30)->default('regular')->after('slug');
            $table->string('posting_policy', 30)->nullable()->after('type');
            $table->boolean('is_system')->default(false)->after('posting_policy');
            $table->boolean('is_pinned')->default(false)->after('is_system');
            $table->boolean('is_archived')->default(false)->after('is_pinned');
            $table->timestamp('archived_at', 0)->nullable()->after('is_archived');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE community_topics
                  ADD CONSTRAINT community_topics_type_check
                    CHECK (type IN ('regular','announcements','archive')),
                  ADD CONSTRAINT community_topics_posting_policy_check
                    CHECK (posting_policy IS NULL OR posting_policy IN ('everyone','moderators_only'))
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_topics DROP CONSTRAINT IF EXISTS community_topics_type_check');
            DB::statement('ALTER TABLE community_topics DROP CONSTRAINT IF EXISTS community_topics_posting_policy_check');
        }

        Schema::table('community_topics', function (Blueprint $table) {
            $table->dropColumn(['type', 'posting_policy', 'is_system', 'is_pinned', 'is_archived', 'archived_at']);
        });
    }
};
