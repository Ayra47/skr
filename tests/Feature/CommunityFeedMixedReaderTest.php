<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Models\User;
use App\Services\FeedItemsReader;
use App\Services\FeedVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunityFeedMixedReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_feed_returns_feed_post_and_community_post_in_sort_order(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $feedPost = $this->createFeedPost($viewer, ['body' => 'feed-post']);
        $communityPost = $this->createCommunityPost(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);

        $this->setFeedItemSortAt(FeedItem::SOURCE_FEED_POST, (string) $feedPost->id, now()->subMinute());
        $this->setFeedItemSortAt(FeedItem::SOURCE_COMMUNITY_POST, $communityPost->id, now());

        $page = $this->reader()->readForFeed($viewer, 'all', 10);

        $this->assertSame([
            FeedItem::SOURCE_COMMUNITY_POST.':'.$communityPost->id,
            FeedItem::SOURCE_FEED_POST.':'.$feedPost->id,
        ], $this->sourceKeys($page->items->all()));
    }

    public function test_cursor_pagination_has_no_duplicates_with_mixed_source_types(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $items = collect();

        for ($i = 0; $i < 3; $i++) {
            $post = $this->createFeedPost($viewer, ['body' => 'feed-'.$i]);
            $this->setFeedItemSortAt(FeedItem::SOURCE_FEED_POST, (string) $post->id, now()->subMinutes($i));
            $items->push(FeedItem::SOURCE_FEED_POST.':'.$post->id);
        }

        for ($i = 0; $i < 3; $i++) {
            $post = $this->createCommunityPost(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);
            $this->setFeedItemSortAt(FeedItem::SOURCE_COMMUNITY_POST, $post->id, now()->subMinutes($i + 3));
            $items->push(FeedItem::SOURCE_COMMUNITY_POST.':'.$post->id);
        }

        $page1 = $this->reader()->readForFeed($viewer, 'all', 3);
        $page2 = $this->reader()->readForFeed($viewer, 'all', 3, $page1->nextCursor);

        $this->assertNotNull($page1->nextCursor);
        $this->assertEmpty(collect($this->sourceKeys($page1->items->all()))->intersect($this->sourceKeys($page2->items->all())));
        $this->assertEqualsCanonicalizing($items->all(), [
            ...$this->sourceKeys($page1->items->all()),
            ...$this->sourceKeys($page2->items->all()),
        ]);
    }

    public function test_overfetch_finds_visible_community_post_after_invisible_items(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $expiredOne = $this->createFeedPost($viewer, ['expires_at' => now()->subHour()]);
        $expiredTwo = $this->createFeedPost($viewer, ['expires_at' => now()->subHour()]);
        $communityPost = $this->createCommunityPost(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);

        $this->setFeedItemSortAt(FeedItem::SOURCE_FEED_POST, (string) $expiredOne->id, now()->addMinutes(2));
        $this->setFeedItemSortAt(FeedItem::SOURCE_FEED_POST, (string) $expiredTwo->id, now()->addMinute());
        $this->setFeedItemSortAt(FeedItem::SOURCE_COMMUNITY_POST, $communityPost->id, now());

        $page = $this->reader()->readForFeed($viewer, 'all', 1, rawBatch: 2);

        $this->assertSame([FeedItem::SOURCE_COMMUNITY_POST.':'.$communityPost->id], $this->sourceKeys($page->items->all()));
    }

    public function test_community_feed_items_flag_false_hides_community_post_items_from_reader(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $this->createCommunityPost(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);

        config(['features.community_feed_items_enabled' => false]);

        $page = $this->reader()->readForFeed($viewer, 'all', 10);

        $this->assertEmpty($page->items);
    }

    public function test_community_feed_items_flag_true_includes_visible_community_post_items(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $communityPost = $this->createCommunityPost(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);

        $page = $this->reader()->readForFeed($viewer, 'all', 10);

        $this->assertSame([FeedItem::SOURCE_COMMUNITY_POST.':'.$communityPost->id], $this->sourceKeys($page->items->all()));
    }

    public function test_groups_tab_returns_only_visible_community_posts(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $feedPost = $this->createFeedPost($viewer, ['body' => 'ordinary-feed-post']);
        $communityPost = $this->createCommunityPost(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);

        $this->setFeedItemSortAt(FeedItem::SOURCE_FEED_POST, (string) $feedPost->id, now()->addMinute());
        $this->setFeedItemSortAt(FeedItem::SOURCE_COMMUNITY_POST, $communityPost->id, now());

        $page = $this->reader()->readForFeed($viewer, 'groups', 10);

        $this->assertSame([FeedItem::SOURCE_COMMUNITY_POST.':'.$communityPost->id], $this->sourceKeys($page->items->all()));
    }

    public function test_groups_tab_searches_only_community_posts(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $this->createFeedPost($viewer, ['body' => 'needle regular feed post']);
        $matchingCommunityPost = $this->createCommunityPost(['body' => 'needle community body', 'visibility' => CommunityPost::VISIBILITY_PUBLIC]);
        $this->createCommunityPost(['body' => 'unmatched community body', 'visibility' => CommunityPost::VISIBILITY_PUBLIC]);

        $page = $this->reader()->readForFeed($viewer, 'groups', 10, communitySearch: 'needle');

        $this->assertSame([FeedItem::SOURCE_COMMUNITY_POST.':'.$matchingCommunityPost->id], $this->sourceKeys($page->items->all()));
    }

    public function test_private_community_post_does_not_appear_for_non_member(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['visibility' => Community::VISIBILITY_PRIVATE],
        );

        $page = $this->reader()->readForFeed($viewer, 'friends', 10);

        $this->assertEmpty($page->items);
    }

    public function test_private_community_post_appears_for_active_member(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $communityPost = $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['visibility' => Community::VISIBILITY_PRIVATE],
        );
        CommunityMember::factory()->for($communityPost->community)->for($viewer)->create([
            'status' => CommunityMember::STATUS_ACTIVE,
        ]);

        $page = $this->reader()->readForFeed($viewer, 'friends', 10);

        $this->assertSame([FeedItem::SOURCE_COMMUNITY_POST.':'.$communityPost->id], $this->sourceKeys($page->items->all()));
    }

    public function test_private_community_post_appears_in_all_tab_for_active_member(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $communityPost = $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['visibility' => Community::VISIBILITY_PRIVATE],
        );
        CommunityMember::factory()->for($communityPost->community)->for($viewer)->create([
            'status' => CommunityMember::STATUS_ACTIVE,
        ]);

        $page = $this->reader()->readForFeed($viewer, 'all', 10);

        $this->assertSame([FeedItem::SOURCE_COMMUNITY_POST.':'.$communityPost->id], $this->sourceKeys($page->items->all()));
    }

    public function test_private_community_post_does_not_appear_in_all_tab_for_non_member(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['visibility' => Community::VISIBILITY_PRIVATE],
        );

        $page = $this->reader()->readForFeed($viewer, 'all', 10);

        $this->assertEmpty($page->items);
    }

    public function test_pending_key_delivery_member_does_not_see_community_post_in_global_feed(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $communityPost = $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['visibility' => Community::VISIBILITY_PRIVATE],
        );
        CommunityMember::factory()->for($communityPost->community)->for($viewer)->pendingKeyDelivery()->create();

        $page = $this->reader()->readForFeed($viewer, 'friends', 10);

        $this->assertEmpty($page->items);
    }

    public function test_allow_posts_in_member_feed_false_hides_item_from_reader(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $viewer = User::factory()->create();
        $communityPost = $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY],
            ['allow_posts_in_member_feed' => false, 'visibility' => Community::VISIBILITY_PRIVATE],
        );
        CommunityMember::factory()->for($communityPost->community)->for($viewer)->create([
            'status' => CommunityMember::STATUS_ACTIVE,
        ]);

        $page = $this->reader()->readForFeed($viewer, 'friends', 10);

        $this->assertEmpty($page->items);
    }

    private function reader(): FeedItemsReader
    {
        return new FeedItemsReader(new FeedVisibilityService);
    }

    private function createFeedPost(User $author, array $attributes = []): FeedPost
    {
        return FeedPost::query()->create(array_merge([
            'user_id' => $author->id,
            'body' => 'feed-post-'.uniqid(),
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ], $attributes));
    }

    private function createCommunityPost(array $postAttributes = [], array $communityAttributes = []): CommunityPost
    {
        $community = Community::factory()->create($communityAttributes);
        $topic = CommunityTopic::factory()->for($community)->create();
        $author = User::factory()->create();

        return CommunityPost::factory()
            ->for($community)
            ->for($topic, 'topic')
            ->for($author, 'author')
            ->create($postAttributes);
    }

    private function setFeedItemSortAt(string $sourceType, string $sourceId, mixed $sortAt): void
    {
        DB::table('feed_items')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->update(['sort_at' => $sortAt]);
    }

    /**
     * @param  array<int, FeedItem>  $items
     * @return array<int, string>
     */
    private function sourceKeys(array $items): array
    {
        return collect($items)
            ->map(fn (FeedItem $item) => $item->source_type.':'.$item->source_id)
            ->values()
            ->all();
    }
}
