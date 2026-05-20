<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->unsignedInteger('member_limit')->nullable()->after('post_count');
            $table->unsignedInteger('default_post_ttl_seconds')->nullable()->after('member_limit');
            $table->boolean('allow_posts_in_member_feed')->default(true)->after('default_post_ttl_seconds');
            $table->string('invite_policy', 30)->default('moderators_only')->after('allow_posts_in_member_feed');
            $table->string('posting_policy', 30)->default('everyone')->after('invite_policy');
            $table->boolean('hide_real_names')->default(false)->after('posting_policy');
            $table->boolean('show_key_fingerprints')->default(true)->after('hide_real_names');
            $table->boolean('anonymous_reactions_enabled')->default(false)->after('show_key_fingerprints');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE communities
                  ADD CONSTRAINT communities_member_limit_check
                    CHECK (member_limit IS NULL OR member_limit IN (5, 50, 100, 500, 1000, 5000)),
                  ADD CONSTRAINT communities_default_post_ttl_seconds_check
                    CHECK (default_post_ttl_seconds IS NULL OR default_post_ttl_seconds IN (3600, 86400, 604800)),
                  ADD CONSTRAINT communities_invite_policy_check
                    CHECK (invite_policy IN ('all_members', 'moderators_only')),
                  ADD CONSTRAINT communities_posting_policy_check
                    CHECK (posting_policy IN ('everyone', 'moderators_only'))
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE communities
                DROP CONSTRAINT IF EXISTS communities_member_limit_check,
                DROP CONSTRAINT IF EXISTS communities_default_post_ttl_seconds_check,
                DROP CONSTRAINT IF EXISTS communities_invite_policy_check,
                DROP CONSTRAINT IF EXISTS communities_posting_policy_check
            ');
        }

        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn([
                'member_limit', 'default_post_ttl_seconds', 'allow_posts_in_member_feed',
                'invite_policy', 'posting_policy', 'hide_real_names',
                'show_key_fingerprints', 'anonymous_reactions_enabled',
            ]);
        });
    }
};
