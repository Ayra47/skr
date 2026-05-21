<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_topic_user_state', function (Blueprint $table) {
            $table->unsignedBigInteger('last_read_topic_seq')->default(0)->after('last_read_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('community_topic_user_state', function (Blueprint $table) {
            $table->dropColumn('last_read_topic_seq');
        });
    }
};
