<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\FeedPost;
use App\Models\ProfileSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityProfileActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_sees_own_community_post_activity_with_default_settings(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $this->createCommunityPost($author, [
            'ciphertext' => 'PROFILE-CIPHERTEXT-OWN',
            'nonce' => 'PROFILE-NONCE-OWN',
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ], [
            'name' => 'Public Profile Community',
            'visibility' => Community::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($author)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertSeeText('Encrypted community post')
            ->assertSeeText('Public Profile Community')
            ->assertDontSee('PROFILE-CIPHERTEXT-OWN')
            ->assertDontSee('PROFILE-NONCE-OWN');
    }

    public function test_community_activity_setting_none_hides_community_activity(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $this->allowCommunityProfileActivity($author, ProfileSetting::AUDIENCE_NONE);

        $this->createCommunityPost($author, ['visibility' => CommunityPost::VISIBILITY_PUBLIC], [
            'name' => 'Hidden By Profile Setting',
            'visibility' => Community::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSeeText('Encrypted community post')
            ->assertDontSeeText('Hidden By Profile Setting');
    }

    public function test_active_member_viewer_sees_private_community_activity_when_setting_allows(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $this->allowCommunityProfileActivity($author, ProfileSetting::AUDIENCE_EVERYONE);

        $post = $this->createCommunityPost($author, [], [
            'name' => 'Private Member Profile Space',
            'visibility' => Community::VISIBILITY_PRIVATE,
        ]);
        CommunityMember::factory()->for($post->community)->for($viewer)->create();

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertSeeText('Encrypted community post')
            ->assertSeeText('Private Member Profile Space');
    }

    public function test_non_member_viewer_does_not_see_private_community_activity(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $this->allowCommunityProfileActivity($author, ProfileSetting::AUDIENCE_EVERYONE);

        $this->createCommunityPost($author, [], [
            'name' => 'Private Non Member Space',
            'visibility' => Community::VISIBILITY_PRIVATE,
        ]);

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSeeText('Encrypted community post')
            ->assertDontSeeText('Private Non Member Space');
    }

    public function test_hidden_community_activity_is_not_leaked_to_non_member(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $this->allowCommunityProfileActivity($author, ProfileSetting::AUDIENCE_EVERYONE);

        $this->createCommunityPost($author, [], [
            'name' => 'Hidden Profile Community',
            'visibility' => Community::VISIBILITY_HIDDEN,
        ]);

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSeeText('Encrypted community post')
            ->assertDontSeeText('Hidden Profile Community');
    }

    public function test_pending_key_delivery_viewer_does_not_see_community_activity(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $this->allowCommunityProfileActivity($author, ProfileSetting::AUDIENCE_EVERYONE);

        $post = $this->createCommunityPost($author, [], [
            'name' => 'Pending Keys Profile Community',
            'visibility' => Community::VISIBILITY_PRIVATE,
        ]);
        CommunityMember::factory()->pendingKeyDelivery()->for($post->community)->for($viewer)->create();

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSeeText('Encrypted community post')
            ->assertDontSeeText('Pending Keys Profile Community');
    }

    public function test_allow_posts_in_member_feed_false_hides_community_profile_activity(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $this->allowCommunityProfileActivity($author, ProfileSetting::AUDIENCE_EVERYONE);

        $post = $this->createCommunityPost($author, [], [
            'allow_posts_in_member_feed' => false,
            'name' => 'No Member Feed Profile Community',
            'visibility' => Community::VISIBILITY_PRIVATE,
        ]);
        CommunityMember::factory()->for($post->community)->for($viewer)->create();

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSeeText('Encrypted community post')
            ->assertDontSeeText('No Member Feed Profile Community');
    }

    public function test_deleted_expired_and_moderation_hidden_community_posts_do_not_appear(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $this->allowCommunityProfileActivity($author, ProfileSetting::AUDIENCE_EVERYONE);

        $deleted = $this->createCommunityPost($author, ['visibility' => CommunityPost::VISIBILITY_PUBLIC], [
            'name' => 'Deleted Profile Community',
        ]);
        $deleted->delete();

        $this->createCommunityPost($author, [
            'expires_at' => now()->subMinute(),
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ], [
            'name' => 'Expired Profile Community',
        ]);

        $this->createCommunityPost($author, [
            'moderation_status' => CommunityPost::MODERATION_HIDDEN,
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ], [
            'name' => 'Moderation Hidden Profile Community',
        ]);

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSeeText('Deleted Profile Community')
            ->assertDontSeeText('Expired Profile Community')
            ->assertDontSeeText('Moderation Hidden Profile Community')
            ->assertDontSeeText('Encrypted community post');
    }

    public function test_community_feed_items_flag_false_hides_community_profile_activity(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $this->createCommunityPost($author, ['visibility' => CommunityPost::VISIBILITY_PUBLIC], [
            'name' => 'Flag False Profile Community',
        ]);

        config(['features.community_feed_items_enabled' => false]);

        $this->actingAs($author)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSeeText('Encrypted community post')
            ->assertDontSeeText('Flag False Profile Community');
    }

    public function test_unified_feed_items_flag_false_preserves_legacy_feed_post_only_profile_activity(): void
    {
        config([
            'features.unified_feed_items_enabled' => false,
            'features.community_feed_items_enabled' => true,
        ]);

        $author = User::factory()->create();
        FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'Legacy profile feed post remains visible',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $this->createCommunityPost($author, ['visibility' => CommunityPost::VISIBILITY_PUBLIC], [
            'name' => 'Legacy Hidden Community Activity',
        ]);

        $this->actingAs($author)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertSeeText('Legacy profile feed post remains visible')
            ->assertDontSeeText('Encrypted community post')
            ->assertDontSeeText('Legacy Hidden Community Activity');
    }

    public function test_profile_activity_renders_community_placeholder_without_encrypted_payloads_or_plaintext(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        $this->createCommunityPost($author, [
            'ciphertext' => 'PROFILE-SECRET-CIPHERTEXT',
            'nonce' => 'PROFILE-SECRET-NONCE',
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ], [
            'name' => 'Safe Rendering Profile Community',
        ]);

        $this->actingAs($author)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertSeeText('Encrypted community post')
            ->assertSeeText('Safe Rendering Profile Community')
            ->assertDontSee('PROFILE-SECRET-CIPHERTEXT')
            ->assertDontSee('PROFILE-SECRET-NONCE')
            ->assertDontSee('PLAINTEXT-MUST-NOT-RENDER');
    }

    public function test_ordinary_feed_post_recent_activity_still_renders_with_community_flag_enabled(): void
    {
        $this->enableUnifiedCommunityFeed();

        $author = User::factory()->create();
        FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'Ordinary profile feed post still renders',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($author)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertSeeText('Ordinary profile feed post still renders');
    }

    private function enableUnifiedCommunityFeed(): void
    {
        config([
            'features.unified_feed_items_enabled' => true,
            'features.community_feed_items_enabled' => true,
        ]);
    }

    private function allowCommunityProfileActivity(User $author, string $audience): void
    {
        $author->profileSetting()->updateOrCreate([], [
            'profile_posts_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
            'community_activity_visibility' => $audience,
            'community_posts_profile_visibility' => $audience,
        ]);
    }

    private function createCommunityPost(
        User $author,
        array $postAttributes = [],
        array $communityAttributes = [],
    ): CommunityPost {
        $community = Community::factory()->create(array_merge([
            'allow_posts_in_member_feed' => true,
            'visibility' => Community::VISIBILITY_PUBLIC,
        ], $communityAttributes));
        $topic = CommunityTopic::factory()->for($community)->create(['name' => 'Profile Topic']);
        CommunityMember::factory()->for($community)->for($author)->create();

        return CommunityPost::factory()
            ->for($community)
            ->for($topic, 'topic')
            ->for($author, 'author')
            ->create($postAttributes);
    }
}
