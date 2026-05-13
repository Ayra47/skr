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
        Schema::table('user_keys', function (Blueprint $table) {
            $table->text('key_backup')->nullable()->after('public_key_jwk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_keys', function (Blueprint $table) {
            $table->dropColumn('key_backup');
        });
    }
};
