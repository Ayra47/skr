<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('feed_posts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        DB::table('feed_posts')
            ->where('is_whisper', true)
            ->update([
                'user_id' => null,
                'visibility' => 'public',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
