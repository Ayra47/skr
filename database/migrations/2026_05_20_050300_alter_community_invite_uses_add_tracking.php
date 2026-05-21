<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_invite_uses', function (Blueprint $table) {
            $table->uuid('community_id')->after('invite_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();
            $table->string('ip_hash', 128)->nullable()->after('used_at');
            $table->string('user_agent_hash', 128)->nullable()->after('ip_hash');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX community_invite_uses_community_idx ON community_invite_uses (community_id, used_at)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS community_invite_uses_community_idx');
        }

        Schema::table('community_invite_uses', function (Blueprint $table) {
            $table->dropForeign(['community_id']);
            $table->dropColumn(['community_id', 'ip_hash', 'user_agent_hash']);
        });
    }
};
