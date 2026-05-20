<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_user_state', function (Blueprint $table) {
            $table->boolean('pinned')->default(false)->after('last_visited_at');
            $table->unsignedBigInteger('last_read_community_seq')->default(0)->after('pinned');
            $table->timestamp('last_activity_seen_at', 0)->nullable()->after('last_read_community_seq');
        });
    }

    public function down(): void
    {
        Schema::table('community_user_state', function (Blueprint $table) {
            $table->dropColumn(['pinned', 'last_read_community_seq', 'last_activity_seen_at']);
        });
    }
};
