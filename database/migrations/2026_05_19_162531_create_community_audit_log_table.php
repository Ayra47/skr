<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_audit_log', function (Blueprint $table) {
            $table->id();

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action', 60);
            $table->jsonb('payload')->nullable();

            $table->timestamp('created_at', 0)->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('
                CREATE INDEX community_audit_log_community_idx
                  ON community_audit_log (community_id, created_at DESC)
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_audit_log');
    }
};
