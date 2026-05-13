<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_keys', function (Blueprint $table) {
            $table->boolean('notify_sound')->default(true)->after('key_changed_at');
            $table->boolean('notify_email')->default(false)->after('notify_sound');
            $table->boolean('notify_email_text')->default(false)->after('notify_email');
            $table->boolean('notify_push')->default(false)->after('notify_email_text');
            $table->boolean('notify_push_text')->default(false)->after('notify_push');
        });
    }

    public function down(): void
    {
        Schema::table('user_keys', function (Blueprint $table) {
            $table->dropColumn(['notify_sound', 'notify_email', 'notify_email_text', 'notify_push', 'notify_push_text']);
        });
    }
};
