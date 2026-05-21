<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('community_posts', 'body')) {
            return;
        }

        Schema::table('community_posts', function (Blueprint $table) {
            $table->text('body')->nullable()->after('epoch_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('community_posts', 'body')) {
            return;
        }

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn('body');
        });
    }
};
