<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_files', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('community_id');
            $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();

            // Nullable: files can be attached to a post or stand alone in a community
            $table->uuid('post_id')->nullable();
            $table->foreign('post_id')->references('id')->on('community_posts')->cascadeOnDelete();

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('name', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_files');
    }
};
