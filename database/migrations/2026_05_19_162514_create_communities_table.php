<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communities', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();

            // open: anyone can join; invite_only: must have invite; request: admin approves
            $table->string('join_mode', 20)->default('open');
            // public: listed and readable; private: listed but members-only; hidden: not listed
            $table->string('visibility', 20)->default('public');

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('post_count')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE communities
                  ADD CONSTRAINT communities_join_mode_check CHECK (join_mode IN ('open','invite_only','request')),
                  ADD CONSTRAINT communities_visibility_check CHECK (visibility IN ('public','private','hidden'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('communities');
    }
};
