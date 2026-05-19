<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Models\User;
use App\Services\FeedItemsReader;
use App\Services\FeedVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnifiedFeedTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePost(User $user, array $attrs = []): FeedPost
    {
        return FeedPost::query()->create(array_merge([
            'user_id' => $user->id,
            'body' => 'test post',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ], $attrs));
    }

    private function reader(): FeedItemsReader
    {
        return new FeedItemsReader(new FeedVisibilityService);
    }

    private function makeFriends(User $a, User $b): void
    {
        DB::table('friends')->insert([
            ['user_id' => $a->id, 'friend_id' => $b->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $b->id, 'friend_id' => $a->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function setSortAt(FeedPost $post, Carbon $sortAt): void
    {
        DB::table('feed_items')
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->where('source_id', (string) $post->id)
            ->update(['sort_at' => $sortAt]);
    }

    // -------------------------------------------------------------------------
    // 1. Cursor pagination produces no duplicates across pages
    // -------------------------------------------------------------------------

    public function test_cursor_pagination_has_no_duplicates(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 10; $i++) {
            $this->makePost($user);
        }

        $page1 = $this->reader()->readForFeed($user, 'all', 5);
        $page2 = $this->reader()->readForFeed($user, 'all', 5, $page1->nextCursor);

        $this->assertCount(5, $page1->items);
        $this->assertCount(5, $page2->items);

        $ids1 = $page1->items->pluck('source_id');
        $ids2 = $page2->items->pluck('source_id');

        $this->assertEmpty($ids1->intersect($ids2));
        $this->assertNotNull($page1->nextCursor);
        $this->assertNull($page2->nextCursor); // exactly 10 posts, page 2 exhausts them
    }

    // -------------------------------------------------------------------------
    // 2. Items are ordered sort_at DESC, id DESC (latest first)
    // -------------------------------------------------------------------------

    public function test_items_are_ordered_by_sort_at_desc_then_id_desc(): void
    {
        $user = $this->makeUser();

        $post1 = $this->makePost($user);
        $post2 = $this->makePost($user);
        $post3 = $this->makePost($user);

        $page = $this->reader()->readForFeed($user, 'all', 25);

        $sourceIds = $page->items->pluck('source_id')->map(fn ($id) => (int) $id)->all();

        $this->assertSame([$post3->id, $post2->id, $post1->id], $sourceIds);
    }

    // -------------------------------------------------------------------------
    // 3. Soft-deleted feed_items are excluded
    // -------------------------------------------------------------------------

    public function test_deleted_feed_items_are_excluded(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        FeedItem::query()
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->where('source_id', (string) $post->id)
            ->update(['deleted_at' => now()]);

        $page = $this->reader()->readForFeed($user, 'all', 25);

        $this->assertEmpty($page->items);
    }

    // -------------------------------------------------------------------------
    // 4. Expired feed_posts are filtered out by FeedVisibilityService
    // -------------------------------------------------------------------------

    public function test_expired_feed_post_is_filtered_out(): void
    {
        $user = $this->makeUser();
        $this->makePost($user, ['expires_at' => now()->subHour()]);

        $page = $this->reader()->readForFeed($user, 'all', 25);

        $this->assertEmpty($page->items);
    }

    // -------------------------------------------------------------------------
    // 5. Friends-only post: visible to friend, hidden from stranger
    // -------------------------------------------------------------------------

    public function test_friends_only_post_visible_to_friend_not_to_stranger(): void
    {
        $author = $this->makeUser();
        $friend = $this->makeUser();
        $stranger = $this->makeUser();

        $this->makeFriends($author, $friend);

        $this->makePost($author, ['visibility' => FeedPost::VISIBILITY_FRIENDS]);

        $friendPage = $this->reader()->readForFeed($friend, 'friends', 25);
        $strangerPage = $this->reader()->readForFeed($stranger, 'friends', 25);

        $this->assertCount(1, $friendPage->items);
        $this->assertEmpty($strangerPage->items);
    }

    // -------------------------------------------------------------------------
    // 6. Whisper post appears with null actor_id and public visibility
    // -------------------------------------------------------------------------

    public function test_whisper_post_appears_with_null_actor_id(): void
    {
        $user = $this->makeUser();
        $this->makePost($user, ['is_whisper' => true]);

        $viewer = $this->makeUser();
        $page = $this->reader()->readForFeed($viewer, 'all', 25);

        $this->assertCount(1, $page->items);

        /** @var FeedItem $item */
        $item = $page->items->first();
        $this->assertNull($item->actor_id);
        $this->assertSame(FeedItem::SCOPE_PUBLIC, $item->visibility_scope);
    }

    // -------------------------------------------------------------------------
    // 7. Feature flag false → Feed.php uses legacy FeedPost query (no FeedItemsReader)
    // -------------------------------------------------------------------------

    public function test_feature_flag_false_uses_legacy_feed_post_query(): void
    {
        config(['features.unified_feed_items_enabled' => false]);

        $user = $this->makeUser();
        $post = $this->makePost($user);

        // Force-delete all feed_items: if unified path were active it would return empty.
        FeedItem::withTrashed()->forceDelete();

        // Legacy path must still return the post (reads directly from feed_posts).
        $this->actingAs($user);

        $response = $this->get(route('feed.index'));

        $response->assertOk();
        $response->assertSee($post->body);
    }

    // -------------------------------------------------------------------------
    // 8–10. tab='all' parity with legacy FeedPost::forTab('all')
    //
    // Legacy: ->where('visibility', 'public') — only public posts.
    // Own friends-only and friends' friends-only posts are NOT included.
    // -------------------------------------------------------------------------

    public function test_all_tab_excludes_viewers_own_friends_only_post_matching_legacy(): void
    {
        $user = $this->makeUser();
        $this->makePost($user, ['visibility' => FeedPost::VISIBILITY_FRIENDS]);

        $page = $this->reader()->readForFeed($user, 'all', 25);

        $this->assertEmpty($page->items, 'all tab must exclude own friends-only posts (legacy parity)');
    }

    public function test_all_tab_excludes_friends_friends_only_post_matching_legacy(): void
    {
        $author = $this->makeUser();
        $viewer = $this->makeUser();
        $this->makeFriends($author, $viewer);

        $this->makePost($author, ['visibility' => FeedPost::VISIBILITY_FRIENDS]);

        $page = $this->reader()->readForFeed($viewer, 'all', 25);

        $this->assertEmpty($page->items, 'all tab must exclude friends-only posts even from friends (legacy parity)');
    }

    public function test_all_tab_excludes_strangers_friends_only_post(): void
    {
        $stranger = $this->makeUser();
        $viewer = $this->makeUser();

        $this->makePost($stranger, ['visibility' => FeedPost::VISIBILITY_FRIENDS]);

        $page = $this->reader()->readForFeed($viewer, 'all', 25);

        $this->assertEmpty($page->items);
    }

    // -------------------------------------------------------------------------
    // 11. Overfetch loop fetches a second rawBatch when first batch is all invisible
    // -------------------------------------------------------------------------

    public function test_overfetch_loop_finds_visible_items_beyond_first_raw_batch(): void
    {
        $user = $this->makeUser();
        $t = now();

        // Visible posts — assign old sort_at so they scan after the invisible ones
        $vis1 = $this->makePost($user);
        $this->setSortAt($vis1, $t->copy()->subSeconds(10));
        $vis2 = $this->makePost($user);
        $this->setSortAt($vis2, $t->copy()->subSeconds(9));

        // Expired posts — assign newer sort_at so they fill the first rawBatch
        for ($i = 1; $i <= 4; $i++) {
            $exp = $this->makePost($user, ['expires_at' => now()->subHour()]);
            $this->setSortAt($exp, $t->copy()->addSeconds($i));
        }

        // rawBatch=3 forces two batches: batch-1 = 3 expired, batch-2 = 1 expired + 2 visible
        $page = $this->reader()->readForFeed($user, 'all', 2, null, rawBatch: 3);

        $this->assertCount(2, $page->items);
        $sourceIds = $page->items->pluck('source_id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($vis1->id, $sourceIds);
        $this->assertContains($vis2->id, $sourceIds);
    }

    // -------------------------------------------------------------------------
    // 12. Cursor based on last scanned item — no items skipped when invisible
    //     items surround visible items in scan order
    // -------------------------------------------------------------------------

    public function test_invisible_items_between_visible_do_not_cause_cursor_to_skip_items(): void
    {
        $user = $this->makeUser();
        $t = now();

        // Desired scan order (DESC sort_at):
        //   vis3 (t+4) → inv2 (t+2) → inv1 (t+1) → vis2 (t) → vis1 (t-4)
        $vis3 = $this->makePost($user);
        $this->setSortAt($vis3, $t->copy()->addSeconds(4));

        $inv2 = $this->makePost($user, ['expires_at' => now()->subHour()]);
        $this->setSortAt($inv2, $t->copy()->addSeconds(2));

        $inv1 = $this->makePost($user, ['expires_at' => now()->subHour()]);
        $this->setSortAt($inv1, $t->copy()->addSeconds(1));

        $vis2 = $this->makePost($user);
        $this->setSortAt($vis2, $t->copy());

        $vis1 = $this->makePost($user);
        $this->setSortAt($vis1, $t->copy()->subSeconds(4));

        // Page 1 (pageSize=2): scans vis3→inv2→inv1→vis2, stops at vis2 (2nd visible).
        // nextCursor must encode vis2's position so page 2 starts from after vis2.
        $page1 = $this->reader()->readForFeed($user, 'all', 2);
        $sourceIds1 = $page1->items->pluck('source_id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$vis3->id, $vis2->id], $sourceIds1);
        $this->assertNotNull($page1->nextCursor);

        // Page 2: starts after vis2, finds only vis1.
        // If cursor were at inv1 (an invisible item scanned before vis2), vis2 would be returned
        // again here — that would be a duplicate, proving the cursor is wrong.
        $page2 = $this->reader()->readForFeed($user, 'all', 2, $page1->nextCursor);
        $sourceIds2 = $page2->items->pluck('source_id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($vis1->id, $sourceIds2);
        $this->assertNotContains($vis2->id, $sourceIds2);
        $this->assertNotContains($vis3->id, $sourceIds2);
    }

    // -------------------------------------------------------------------------
    // 13. Feature flag true — FeedItemsReader can serve a second cursor page
    // -------------------------------------------------------------------------

    public function test_feature_flag_true_can_load_second_cursor_page(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 30; $i++) {
            $this->makePost($user);
        }

        $page1 = $this->reader()->readForFeed($user, 'all', 25);
        $this->assertCount(25, $page1->items);
        $this->assertNotNull($page1->nextCursor);

        $page2 = $this->reader()->readForFeed($user, 'all', 25, $page1->nextCursor);
        $this->assertCount(5, $page2->items);
        $this->assertNull($page2->nextCursor);

        $this->assertEmpty(
            $page1->items->pluck('source_id')->intersect($page2->items->pluck('source_id'))
        );
    }
}
