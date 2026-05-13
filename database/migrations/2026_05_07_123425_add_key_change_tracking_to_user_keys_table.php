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
            // 'fresh' = new device without PIN/backup restore; 'settings' = intentional update via settings
            $table->string('key_change_source', 10)->nullable()->after('key_backup');
            $table->timestamp('key_changed_at')->nullable()->after('key_change_source');
        });
    }

    public function down(): void
    {
        Schema::table('user_keys', function (Blueprint $table) {
            $table->dropColumn(['key_change_source', 'key_changed_at']);
        });
    }
};
