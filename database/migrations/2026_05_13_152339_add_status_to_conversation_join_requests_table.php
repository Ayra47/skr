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
        Schema::table('conversation_join_requests', function (Blueprint $table) {
            $table->string('status', 12)->default('pending')->after('invited_by_id');
            $table->index(['invited_user_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_join_requests', function (Blueprint $table) {
            $table->dropIndex(['invited_user_id', 'status', 'created_at']);
            $table->dropColumn('status');
        });
    }
};
