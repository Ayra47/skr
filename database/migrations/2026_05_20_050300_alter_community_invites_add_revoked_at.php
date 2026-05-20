<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_invites', function (Blueprint $table) {
            $table->timestamp('revoked_at', 0)->nullable()->after('is_revoked');
        });
    }

    public function down(): void
    {
        Schema::table('community_invites', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }
};
