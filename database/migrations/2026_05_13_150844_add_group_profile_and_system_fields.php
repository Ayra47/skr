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
            $table->string('avatar')->nullable()->after('title');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('type', 12)->default('message')->after('id')->index();
            $table->json('system_payload')->nullable()->after('encrypted_payload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'system_payload']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
