<?php

namespace Tests\Feature;

use App\Models\ProfileSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function visibilityPayload(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }

    public function test_user_can_update_profile_visibility_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('settings.profile-visibility.update'), $this->visibilityPayload())
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

    public function test_invalid_community_activity_profile_setting_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('settings.profile-visibility.update'), $this->visibilityPayload([
                'community_posts_profile_visibility' => 'invalid',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('community_posts_profile_visibility');
    }

    public function test_community_activity_profile_setting_defaults_work(): void
    {
        $user = User::factory()->create();

        $settings = $user->profileSetting()->create([])->refresh();

        $this->assertSame(ProfileSetting::AUDIENCE_FRIENDS, $settings->community_activity_visibility);
        $this->assertSame(ProfileSetting::AUDIENCE_FRIENDS, $settings->community_posts_profile_visibility);
    }
}
