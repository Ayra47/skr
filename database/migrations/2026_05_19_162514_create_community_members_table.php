<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_members', function (Blueprint $table) {
            $table->id();

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('role', 20)->default('member');
            $table->timestamp('joined_at', 0)->useCurrent();

            $table->timestamps();

            $table->unique(['community_id', 'user_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE community_members
                  ADD CONSTRAINT community_members_role_check
                    CHECK (role IN ('owner','admin','moderator','member'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_members');
    }
};
