<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('friend_requests', function ($table) {
            $table->dropUnique('unique_pending_request');
        });

        // Only one pending request per sender-receiver pair allowed
        DB::statement(
            "CREATE UNIQUE INDEX unique_pending_request ON friend_requests (sender_id, receiver_id) WHERE status = 'pending'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_pending_request');

        Schema::table('friend_requests', function ($table) {
            $table->unique(['sender_id', 'receiver_id', 'status'], 'unique_pending_request');
        });
    }
};
