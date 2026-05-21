<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_topics', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('slug', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->unsignedInteger('post_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['community_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_topics');
    }
};
