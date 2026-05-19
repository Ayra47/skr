<?php

namespace Tests\Feature;

use App\Models\ProfileSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_visibility_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('settings.profile-visibility.update'), [
                'show_shared_chats' => false,
                'show_shared_groups' => false,
                'profile_access' => ProfileSetting::AUDIENCE_FRIENDS,
                'online_status_visibility' => ProfileSetting::AUDIENCE_NONE,
                'shared_friends_count_visibility' => ProfileSetting::AUDIENCE_FRIENDS,
                'feed_posts_count_visibility' => ProfileSetting::AUDIENCE_NONE,
                'profile_posts_visibility' => ProfileSetting::AUDIENCE_FRIENDS,
                'avatar_visibility' => ProfileSetting::AUDIENCE_NONE,
                'profile_communities_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
                'community_activity_visibility' => ProfileSetting::AUDIENCE_FRIENDS,
                'community_posts_profile_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
                'community_posts_feed_visibility' => ProfileSetting::AUDIENCE_NONE,
                'joined_communities_activity_visibility' => ProfileSetting::AUDIENCE_FRIENDS,
                'community_roles_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('profile_settings', [
            'user_id' => $user->id,
            'show_shared_chats' => false,
            'show_shared_groups' => false,
            'profile_access' => ProfileSetting::AUDIENCE_FRIENDS,
            'online_status_visibility' => ProfileSetting::AUDIENCE_NONE,
            'shared_friends_count_visibility' => ProfileSetting::AUDIENCE_FRIENDS,
            'feed_posts_count_visibility' => ProfileSetting::AUDIENCE_NONE,
            'profile_posts_visibility' => ProfileSetting::AUDIENCE_FRIENDS,
            'avatar_visibility' => ProfileSetting::AUDIENCE_NONE,
            'profile_communities_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
            'community_activity_visibility' => ProfileSetting::AUDIENCE_FRIENDS,
            'community_posts_profile_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
            'community_posts_feed_visibility' => ProfileSetting::AUDIENCE_NONE,
            'joined_communities_activity_visibility' => ProfileSetting::AUDIENCE_FRIENDS,
            'community_roles_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
        ]);
    }
}
