<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookmark_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookmark_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmark_attachments');
    }
};
