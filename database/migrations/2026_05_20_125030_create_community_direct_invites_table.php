<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_direct_invites', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 30)->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('expires_at', 0)->nullable();
            $table->timestamp('responded_at', 0)->nullable();

            $table->timestamps(0);
        });

        DB::statement("
            ALTER TABLE community_direct_invites
              ADD CONSTRAINT community_direct_invites_different_users_check
                CHECK (inviter_id <> invitee_id),
              ADD CONSTRAINT community_direct_invites_status_check
                CHECK (status IN ('pending','accepted','declined','cancelled','expired'))
        ");

        DB::statement("
            CREATE UNIQUE INDEX community_direct_invites_pending_unique
              ON community_direct_invites (community_id, invitee_id)
              WHERE status = 'pending'
        ");

        DB::statement('
            CREATE INDEX community_direct_invites_invitee_status_created_idx
              ON community_direct_invites (invitee_id, status, created_at DESC)
        ');

        DB::statement('
            CREATE INDEX community_direct_invites_inviter_created_idx
              ON community_direct_invites (inviter_id, created_at DESC)
        ');

        DB::statement('
            CREATE INDEX community_direct_invites_community_status_idx
              ON community_direct_invites (community_id, status)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('community_direct_invites');
    }
};
