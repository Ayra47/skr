<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_members', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->after('role');
            $table->timestamp('left_at', 0)->nullable()->after('joined_at');
            $table->timestamp('banned_at', 0)->nullable()->after('left_at');
            $table->timestamp('suspended_until', 0)->nullable()->after('banned_at');
            $table->string('ban_reason_code', 50)->nullable()->after('suspended_until');
            $table->text('encrypted_ban_note')->nullable()->after('ban_reason_code');
            $table->string('community_display_name', 100)->nullable()->after('encrypted_ban_note');
            $table->string('pseudonym', 100)->nullable()->after('community_display_name');
            $table->string('avatar_color', 20)->nullable()->after('pseudonym');
            $table->string('public_key_fingerprint', 128)->nullable()->after('avatar_color');
            $table->timestamp('last_seen_at', 0)->nullable()->after('public_key_fingerprint');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE community_members
                  ADD CONSTRAINT community_members_status_check
                    CHECK (status IN ('active','pending_key_delivery','left','banned','suspended')),
                  ADD CONSTRAINT community_members_ban_reason_code_check
                    CHECK (ban_reason_code IS NULL OR ban_reason_code IN
                      ('spam','harassment','inappropriate_content','rule_violation','other'))
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_members
                DROP CONSTRAINT IF EXISTS community_members_status_check,
                DROP CONSTRAINT IF EXISTS community_members_ban_reason_code_check
            ');
        }

        Schema::table('community_members', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'left_at', 'banned_at', 'suspended_until', 'ban_reason_code',
                'encrypted_ban_note', 'community_display_name', 'pseudonym',
                'avatar_color', 'public_key_fingerprint', 'last_seen_at',
            ]);
        });
    }
};
