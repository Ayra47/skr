<?php

namespace Tests\Feature;

use App\Livewire\Feed;
use App\Models\Bookmark;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommunityFeedRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_post_card_renders_encrypted_placeholder(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $this->createCommunityPost(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);

        Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => 'all'])
            ->assertSee('Encrypted community post')
            ->assertSee('Encrypted post');
    }

    public function test_community_post_card_does_not_render_ciphertext_or_nonce(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $this->createCommunityPost([
            'ciphertext' => 'CIPHERTEXT-MUST-NOT-RENDER',
            'nonce' => 'NONCE-MUST-NOT-RENDER',
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ]);

        Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => 'all'])
            ->assertDontSee('CIPHERTEXT-MUST-NOT-RENDER', false)
            ->assertDontSee('NONCE-MUST-NOT-RENDER', false);
    }

    public function test_public_community_name_appears_for_visible_public_community_post(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_PUBLIC],
            ['name' => 'Public Community Feed Name', 'visibility' => Community::VISIBILITY_PUBLIC],
        );

        Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => 'all'])
            ->assertSee('Public Community Feed Name');
    }

    public function test_private_community_name_appears_only_for_active_member(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $member = User::factory()->create();
        $nonMember = User::factory()->create();
        $communityPost = $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['name' => 'Private Community Feed Name', 'visibility' => Community::VISIBILITY_PRIVATE],
        );
        CommunityMember::factory()->for($communityPost->community)->for($member)->create([
            'status' => CommunityMember::STATUS_ACTIVE,
        ]);

        Livewire::actingAs($member)
            ->test(Feed::class, ['tab' => 'friends'])
            ->assertSee('Private Community Feed Name');

        Livewire::actingAs($nonMember)
            ->test(Feed::class, ['tab' => 'friends'])
            ->assertDontSee('Private Community Feed Name');
    }

    public function test_ordinary_feed_posts_still_render_with_community_feed_enabled(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        FeedPost::query()->create([
            'user_id' => $viewer->id,
            'body' => 'ordinary feed post still renders',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => 'mine'])
            ->assertSee('ordinary feed post still renders');
    }

    public function test_bookmark_state_for_feed_posts_still_works_with_community_feed_enabled(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $post = FeedPost::query()->create([
            'user_id' => $viewer->id,
            'body' => 'bookmarked feed post still renders',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $bookmark = Bookmark::query()->create([
            'user_id' => $viewer->id,
            'bookmarkable_type' => FeedPost::class,
            'bookmarkable_id' => $post->id,
            'snapshot_body' => $post->body,
            'snapshot_posted_at' => $post->created_at,
        ]);

        Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => 'mine'])
            ->assertSee('data-bookmarked="true"', false)
            ->assertSee('data-bookmark-id="'.$bookmark->id.'"', false);
    }

    public function test_community_feed_flag_false_keeps_old_unified_feed_behavior_for_feed_posts(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        FeedPost::query()->create([
            'user_id' => $viewer->id,
            'body' => 'flag false feed post remains visible',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_PUBLIC],
            ['name' => 'Flag False Community Should Not Render'],
        );

        config(['features.community_feed_items_enabled' => false]);

        Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => 'all'])
            ->assertSee('flag false feed post remains visible')
            ->assertDontSee('Flag False Community Should Not Render')
            ->assertDontSee('Encrypted community post');
    }

    private function createCommunityPost(array $postAttributes = [], array $communityAttributes = []): CommunityPost
    {
        $community = Community::factory()->create($communityAttributes);
        $topic = CommunityTopic::factory()->for($community)->create(['name' => 'General']);
        $author = User::factory()->create(['pseudonym' => 'community-author']);

        return CommunityPost::factory()
            ->for($community)
            ->for($topic, 'topic')
            ->for($author, 'author')
            ->create($postAttributes);
    }
}
