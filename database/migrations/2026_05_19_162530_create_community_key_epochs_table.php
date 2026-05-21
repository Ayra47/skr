<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_key_epochs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->unsignedInteger('epoch_number');

            // Why the epoch was created
            $table->string('reason', 30)->default('initial');

            $table->timestamp('created_at', 0)->useCurrent();

            $table->unique(['community_id', 'epoch_number']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE community_key_epochs
                  ADD CONSTRAINT community_key_epochs_reason_check
                    CHECK (reason IN ('initial','member_left','member_removed','periodic'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_key_epochs');
    }
};
