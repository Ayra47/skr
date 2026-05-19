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
        Schema::table('profile_settings', function (Blueprint $table) {
            $table->string('profile_communities_visibility', 12)->default('friends');
            $table->string('community_activity_visibility', 12)->default('friends');
            $table->string('community_posts_profile_visibility', 12)->default('friends');
            $table->string('community_posts_feed_visibility', 12)->default('friends');
            $table->string('joined_communities_activity_visibility', 12)->default('friends');
            $table->string('community_roles_visibility', 12)->default('friends');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profile_settings', function (Blueprint $table) {
            $table->dropColumn([
                'profile_communities_visibility',
                'community_activity_visibility',
                'community_posts_profile_visibility',
                'community_posts_feed_visibility',
                'joined_communities_activity_visibility',
                'community_roles_visibility',
            ]);
        });
    }
};
