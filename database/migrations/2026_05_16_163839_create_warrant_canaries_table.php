<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warrant_canaries', function (Blueprint $table) {
            $table->id();
            $table->string('signature', 64);
            $table->boolean('is_current')->default(false);
            $table->timestamp('published_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warrant_canaries');
    }
};
