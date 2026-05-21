<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_member_keys', function (Blueprint $table) {
            $table->uuid('device_key_id')->after('user_id');
            $table->foreign('device_key_id')->references('id')->on('user_device_keys')->cascadeOnDelete();
        });

        // Replace unique(community_id, epoch_id, user_id) with unique(community_id, epoch_id, device_key_id)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_member_keys DROP CONSTRAINT IF EXISTS community_member_keys_community_id_epoch_id_user_id_unique');
            DB::statement('CREATE UNIQUE INDEX community_member_keys_per_device_unique ON community_member_keys (community_id, epoch_id, device_key_id)');
            DB::statement('CREATE INDEX community_member_keys_epoch_idx ON community_member_keys (community_id, epoch_id)');
            DB::statement('CREATE INDEX community_member_keys_user_idx ON community_member_keys (user_id)');
            DB::statement('CREATE INDEX community_member_keys_device_idx ON community_member_keys (device_key_id)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS community_member_keys_per_device_unique');
            DB::statement('DROP INDEX IF EXISTS community_member_keys_epoch_idx');
            DB::statement('DROP INDEX IF EXISTS community_member_keys_user_idx');
            DB::statement('DROP INDEX IF EXISTS community_member_keys_device_idx');
        }

        Schema::table('community_member_keys', function (Blueprint $table) {
            $table->dropForeign(['device_key_id']);
            $table->dropColumn('device_key_id');
            $table->unique(['community_id', 'epoch_id', 'user_id']);
        });
    }
};
