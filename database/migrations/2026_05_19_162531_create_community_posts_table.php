<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->uuid('topic_id')->nullable();
            $table->foreign('topic_id')->references('id')->on('community_topics')->nullOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->uuid('epoch_id')->nullable();
            $table->foreign('epoch_id')->references('id')->on('community_key_epochs')->nullOnDelete();

            $table->text('body')->nullable();

            // public: visible to non-members; members_only: members only; private: E2EE
            $table->string('visibility', 20)->default('members_only');

            $table->boolean('is_pinned')->default(false);

            $table->unsignedInteger('reaction_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);

            $table->timestamp('expires_at', 0)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE community_posts
                  ADD CONSTRAINT community_posts_visibility_check
                    CHECK (visibility IN ('public','members_only','private'))
            ");

            DB::statement('
                CREATE INDEX community_posts_community_idx
                  ON community_posts (community_id, created_at DESC)
                  WHERE deleted_at IS NULL
            ');

            DB::statement('
                CREATE INDEX community_posts_topic_idx
                  ON community_posts (topic_id, created_at DESC)
                  WHERE deleted_at IS NULL AND topic_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
