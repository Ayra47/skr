<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('component_id')->nullable();
            $table->enum('kind', ['info', 'warn', 'crit'])->default('warn');
            $table->enum('status', ['ongoing', 'resolved'])->default('ongoing');
            $table->text('body')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_incidents');
    }
};
