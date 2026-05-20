<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\FeedItem;
use App\Models\User;
use App\Services\FeedVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityFeedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_community_public_post_visible_to_authenticated_viewer(): void
    {
        $viewer = User::factory()->create();
        [$item] = $this->createCommunityPostFeedItem(
            ['visibility' => CommunityPost::VISIBILITY_PUBLIC],
            ['visibility' => Community::VISIBILITY_PUBLIC],
        );

        $this->assertTrue($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_public_community_members_only_post_hidden_from_non_member(): void
    {
        $viewer = User::factory()->create();
        [$item] = $this->createCommunityPostFeedItem(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['visibility' => Community::VISIBILITY_PUBLIC],
        );

        $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_public_community_private_post_hidden_from_non_member(): void
    {
        $viewer = User::factory()->create();
        [$item] = $this->createCommunityPostFeedItem(
            ['visibility' => CommunityPost::VISIBILITY_PRIVATE],
            ['visibility' => Community::VISIBILITY_PUBLIC],
        );

        $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_private_community_post_visible_to_active_member(): void
    {
        $viewer = User::factory()->create();
        [$item, $post] = $this->createCommunityPostFeedItem(
            ['visibility' => CommunityPost::VISIBILITY_PUBLIC],
            ['visibility' => Community::VISIBILITY_PRIVATE],
        );
        CommunityMember::factory()
            ->for($post->community)
            ->for($viewer)
            ->create(['status' => CommunityMember::STATUS_ACTIVE]);

        $this->assertTrue($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_private_community_post_hidden_from_non_member(): void
    {
        $viewer = User::factory()->create();
        [$item] = $this->createCommunityPostFeedItem(
            ['visibility' => CommunityPost::VISIBILITY_PUBLIC],
            ['visibility' => Community::VISIBILITY_PRIVATE],
        );

        $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_hidden_community_post_hidden_from_non_member(): void
    {
        $viewer = User::factory()->create();
        [$item] = $this->createCommunityPostFeedItem(
            ['visibility' => CommunityPost::VISIBILITY_PUBLIC],
            ['visibility' => Community::VISIBILITY_HIDDEN],
        );

        $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_pending_key_delivery_member_does_not_see_community_post_in_global_feed(): void
    {
        $viewer = User::factory()->create();
        [$item, $post] = $this->createCommunityPostFeedItem(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['visibility' => Community::VISIBILITY_PRIVATE],
        );
        CommunityMember::factory()
            ->for($post->community)
            ->for($viewer)
            ->pendingKeyDelivery()
            ->create();

        $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_banned_suspended_and_left_members_do_not_see_community_post(): void
    {
        foreach ([CommunityMember::STATUS_BANNED, CommunityMember::STATUS_SUSPENDED, CommunityMember::STATUS_LEFT] as $status) {
            $viewer = User::factory()->create();
            [$item, $post] = $this->createCommunityPostFeedItem(
                ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
                ['visibility' => Community::VISIBILITY_PRIVATE],
            );
            CommunityMember::factory()
                ->for($post->community)
                ->for($viewer)
                ->create(['status' => $status]);

            $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item), "Status {$status} must not see community post.");
        }
    }

    public function test_allow_posts_in_member_feed_false_hides_item_even_from_active_member(): void
    {
        $viewer = User::factory()->create();
        [$item, $post] = $this->createCommunityPostFeedItem(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['allow_posts_in_member_feed' => false, 'visibility' => Community::VISIBILITY_PRIVATE],
        );
        CommunityMember::factory()
            ->for($post->community)
            ->for($viewer)
            ->create(['status' => CommunityMember::STATUS_ACTIVE]);

        $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_expired_post_hidden(): void
    {
        $viewer = User::factory()->create();
        [$item] = $this->createCommunityPostFeedItem([
            'expires_at' => now()->subMinute(),
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ]);

        $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_deleted_post_hidden(): void
    {
        $viewer = User::factory()->create();
        [$item, $post] = $this->createCommunityPostFeedItem([
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ]);
        $post->delete();

        $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    public function test_hidden_moderation_post_hidden(): void
    {
        $viewer = User::factory()->create();
        [$item] = $this->createCommunityPostFeedItem([
            'moderation_status' => CommunityPost::MODERATION_HIDDEN,
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ]);

        $this->assertFalse($this->service()->canViewerSeeFeedItem($viewer, $item));
    }

    private function service(): FeedVisibilityService
    {
        return new FeedVisibilityService;
    }

    /**
     * @return array{0: FeedItem, 1: CommunityPost}
     */
    private function createCommunityPostFeedItem(array $postAttributes = [], array $communityAttributes = []): array
    {
        $community = Community::factory()->create($communityAttributes);
        $topic = CommunityTopic::factory()->for($community)->create();
        $author = User::factory()->create();

        $post = CommunityPost::factory()
            ->for($community)
            ->for($topic, 'topic')
            ->for($author, 'author')
            ->create($postAttributes);

        $item = FeedItem::query()->create([
            'actor_id' => $post->user_id,
            'item_type' => FeedItem::ITEM_COMMUNITY_POST_CREATED,
            'source_type' => FeedItem::SOURCE_COMMUNITY_POST,
            'source_id' => $post->id,
            'community_id' => $post->community_id,
            'topic_id' => $post->topic_id,
            'post_id' => $post->id,
            'visibility_scope' => $community->visibility === Community::VISIBILITY_PUBLIC && $post->visibility === CommunityPost::VISIBILITY_PUBLIC
                ? FeedItem::SCOPE_PUBLIC
                : FeedItem::SCOPE_COMMUNITY_MEMBERS_ONLY,
            'show_in_feed' => true,
            'show_in_profile_activity' => false,
            'sort_at' => $post->created_at,
        ]);

        return [$item, $post];
    }
}
