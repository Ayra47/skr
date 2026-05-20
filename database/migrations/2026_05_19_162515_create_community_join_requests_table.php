<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_join_requests', function (Blueprint $table) {
            $table->id();

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 20)->default('pending');
            $table->text('message')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at', 0)->nullable();

            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE community_join_requests
                  ADD CONSTRAINT community_join_requests_status_check
                    CHECK (status IN ('pending','approved','rejected','withdrawn'))
            ");

            // Only one pending request per user per community
            DB::statement('
                CREATE UNIQUE INDEX community_join_requests_pending_unique
                  ON community_join_requests (community_id, user_id)
                  WHERE status = \'pending\'
            ');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS community_join_requests_pending_unique');
        }

        Schema::dropIfExists('community_join_requests');
    }
};
