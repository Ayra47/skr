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
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type', 10)->default('direct')->after('id')->index();
            $table->string('title', 60)->nullable()->after('type');
            $table->foreignId('user_a_id')->nullable()->change();
            $table->foreignId('user_b_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['type', 'title']);
            $table->foreignId('user_a_id')->nullable(false)->change();
            $table->foreignId('user_b_id')->nullable(false)->change();
        });
    }
};
