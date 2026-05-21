<?php

namespace Tests\Feature;

use App\Livewire\Feed;
use App\Models\Community;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\ProfileSetting;
use App\Models\User;
use App\Services\Community\CommunityPolicyService;
use App\Services\Community\CommunityPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommunityPlaintextPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_publish_plaintext_community_post_without_encrypted_payload(): void
    {
        $author = User::factory()->create();
        [$community, $topic] = $this->communityContext($author);

        $this->actingAs($author)
            ->postJson(route('communities.topics.posts.store', [$community, $topic]), [
                'body' => 'Plaintext community MVP post',
                'visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY,
            ])
            ->assertCreated()
            ->assertJsonStructure(['success', 'post' => ['id', 'community_seq']]);

        $post = CommunityPost::where('community_id', $community->id)->firstOrFail();
        $this->assertSame('Plaintext community MVP post', $post->body);
        $this->assertNull($post->ciphertext);
        $this->assertNull($post->nonce);
        $this->assertNull($post->epoch_id);
        $this->assertTrue($post->isPlaintext());
        $this->assertFalse($post->isEncrypted());
    }

    public function test_empty_community_post_payload_is_rejected(): void
    {
        $author = User::factory()->create();
        [$community, $topic] = $this->communityContext($author);

        $this->actingAs($author)
            ->postJson(route('communities.topics.posts.store', [$community, $topic]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_community_detail_renders_plaintext_body_and_keeps_encrypted_fallback_safe(): void
    {
        $author = User::factory()->create();
        [$community, $topic, $epoch] = $this->communityContext($author);

        $this->postService()->publishPost($author, $community, $topic, [
            'body' => 'Visible body inside community',
        ]);
        $this->postService()->publishPost($author, $community->fresh(), $topic->fresh(), [
            'ciphertext' => 'LEGACY-CIPHERTEXT-HIDDEN',
            'nonce' => 'LEGACY-NONCE-HIDDEN',
            'epoch_id' => $epoch->id,
        ]);

        $this->actingAs($author)
            ->get(route('communities.show', ['community' => $community, 'topic' => $topic->id]))
            ->assertOk()
            ->assertSeeText('Visible body inside community')
            ->assertSeeText('Encrypted post')
            ->assertDontSee('LEGACY-CIPHERTEXT-HIDDEN')
            ->assertDontSee('LEGACY-NONCE-HIDDEN');
    }

    public function test_plaintext_body_renders_in_global_feed_for_visible_community_post(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $author = User::factory()->create();
        [$community, $topic] = $this->communityContext($author, [
            'name' => 'Plain Feed Community',
            'visibility' => Community::VISIBILITY_PUBLIC,
        ]);
        $this->postService()->publishPost($author, $community, $topic, [
            'body' => 'Plain body in global feed',
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ]);

        Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => 'all'])
            ->assertSee('Plain body in global feed')
            ->assertSee('Plain Feed Community')
            ->assertDontSee('Encrypted community post');
    }

    public function test_private_plaintext_body_does_not_leak_to_non_member_feed_or_profile(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        [$community, $topic] = $this->communityContext($author, [
            'name' => 'Private Plain Body Community',
            'visibility' => Community::VISIBILITY_PRIVATE,
        ]);
        $this->allowCommunityProfileActivity($author);
        $this->postService()->publishPost($author, $community, $topic, [
            'body' => 'PRIVATE BODY MUST NOT LEAK',
        ]);

        Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => 'friends'])
            ->assertDontSee('PRIVATE BODY MUST NOT LEAK')
            ->assertDontSee('Private Plain Body Community');

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSee('PRIVATE BODY MUST NOT LEAK')
            ->assertDontSeeText('Private Plain Body Community');
    }

    public function test_plaintext_body_renders_in_profile_activity_when_viewer_has_access(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        [$community, $topic] = $this->communityContext($author, [
            'name' => 'Profile Plain Community',
            'visibility' => Community::VISIBILITY_PRIVATE,
        ]);
        CommunityMember::factory()->for($community)->for($viewer)->create();
        $this->allowCommunityProfileActivity($author);
        $this->postService()->publishPost($author, $community, $topic, [
            'body' => 'Plain body in profile activity',
        ]);

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertSeeText('Plain body in profile activity')
            ->assertSeeText('Profile Plain Community');
    }

    public function test_community_bookmark_renders_plaintext_body_without_copying_snapshot(): void
    {
        $author = User::factory()->create();
        [$community, $topic] = $this->communityContext($author, [
            'name' => 'Bookmark Plain Community',
        ]);
        $post = $this->postService()->publishPost($author, $community, $topic, [
            'body' => 'Plain body in bookmark',
        ]);

        $this->actingAs($author)
            ->postJson(route('bookmarks.store'), [
                'bookmarkable_type' => 'community_post',
                'bookmarkable_id' => $post->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('bookmarks', [
            'bookmarkable_type' => CommunityPost::class,
            'bookmarkable_key' => $post->id,
            'snapshot_body' => null,
        ]);

        $this->actingAs($author)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSeeText('Plain body in bookmark')
            ->assertSeeText('Bookmark Plain Community');
    }

    public function test_access_lost_bookmark_does_not_render_plaintext_body_or_private_metadata(): void
    {
        $author = User::factory()->create();
        [$community, $topic] = $this->communityContext($author, [
            'name' => 'Hidden Bookmark Plain Community',
            'visibility' => Community::VISIBILITY_HIDDEN,
        ]);
        $post = $this->postService()->publishPost($author, $community, $topic, [
            'body' => 'LOCKED BODY MUST NOT RENDER',
        ]);

        $this->actingAs($author)
            ->postJson(route('bookmarks.store'), [
                'bookmarkable_type' => 'community_post',
                'bookmarkable_id' => $post->id,
            ])
            ->assertCreated();

        CommunityMember::where('community_id', $community->id)
            ->where('user_id', $author->id)
            ->update(['status' => CommunityMember::STATUS_LEFT, 'left_at' => now()]);

        $this->actingAs($author)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSeeText('Community post unavailable')
            ->assertDontSee('LOCKED BODY MUST NOT RENDER')
            ->assertDontSeeText('Hidden Bookmark Plain Community');
    }

    public function test_expired_plaintext_post_is_hidden_from_feed_profile_and_bookmark_body(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $author = User::factory()->create();
        [$community, $topic] = $this->communityContext($author, [
            'name' => 'Expiring Plain Community',
            'visibility' => Community::VISIBILITY_PUBLIC,
        ]);
        $this->allowCommunityProfileActivity($author);
        $post = $this->postService()->publishPost($author, $community, $topic, [
            'body' => 'EXPIRING BODY MUST DISAPPEAR',
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ]);
        $this->actingAs($author)->postJson(route('bookmarks.store'), [
            'bookmarkable_type' => 'community_post',
            'bookmarkable_id' => $post->id,
        ])->assertCreated();

        $post->update(['expires_at' => now()->subMinute()]);
        $this->artisan('communities:expire-posts')->assertSuccessful();

        Livewire::actingAs($author)
            ->test(Feed::class, ['tab' => 'all'])
            ->assertDontSee('EXPIRING BODY MUST DISAPPEAR');

        $this->actingAs($author)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSee('EXPIRING BODY MUST DISAPPEAR');

        $this->actingAs($author)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSeeText('Community post unavailable')
            ->assertDontSee('EXPIRING BODY MUST DISAPPEAR');
    }

    /**
     * @return array{0: Community, 1: CommunityTopic, 2: CommunityKeyEpoch}
     */
    private function communityContext(User $author, array $communityAttrs = []): array
    {
        $community = Community::factory()->create(array_merge([
            'allow_posts_in_member_feed' => true,
            'visibility' => Community::VISIBILITY_PUBLIC,
            'post_count' => 0,
        ], $communityAttrs));
        $topic = CommunityTopic::factory()->for($community)->create(['name' => 'Plain Topic', 'post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();
        CommunityMember::factory()->for($community)->for($author)->create();

        return [$community, $topic, $epoch];
    }

    private function postService(): CommunityPostService
    {
        return new CommunityPostService(new CommunityPolicyService);
    }

    private function allowCommunityProfileActivity(User $author): void
    {
        $author->profileSetting()->updateOrCreate([], [
            'community_activity_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
            'community_posts_profile_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
        ]);
    }
}
