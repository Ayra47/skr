<?php

namespace Tests\Feature;

use App\Livewire\Feed;
use App\Models\Bookmark;
use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies parity between the legacy FeedPost query path and the unified
 * feed_items path for all three tabs (all / friends / mine), bookmark state
 * resolution, cursor pagination through Feed.php, and backfill idempotency.
 *
 * Community source types are NOT enabled here — they must remain stubbed.
 * Default config flag (features.unified_feed_items_enabled) must stay false.
 */
class FeedParityTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        static $seq = 0;
        $seq++;

        return User::factory()->create([
            'login' => "user{$seq}",
            'name' => "user{$seq}",
            'email' => null,
        ]);
    }

    private function makePost(User $user, array $attrs = []): FeedPost
    {
        return FeedPost::query()->create(array_merge([
            'user_id' => $user->id,
            'body' => 'post-'.uniqid(),
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ], $attrs));
    }

    private function befriend(User $a, User $b): void
    {
        DB::table('friends')->insert([
            ['user_id' => $a->id, 'friend_id' => $b->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $b->id, 'friend_id' => $a->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** Return post body strings from a rendered Feed Livewire component's $posts. */
    private function feedBodies(User $viewer, string $tab, bool $unifiedFlag): array
    {
        config(['features.unified_feed_items_enabled' => $unifiedFlag]);

        $posts = Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => $tab])
            ->viewData('posts');

        return collect($posts->items())->pluck('body')->sort()->values()->all();
    }

    // =========================================================================
    // Part 1 — Tab parity: legacy ↔ unified produce identical post sets
    // =========================================================================

    // -------------------------------------------------------------------------
    // 1a. tab=all — only public posts, both paths
    // -------------------------------------------------------------------------

    public function test_all_tab_legacy_and_unified_show_same_public_posts(): void
    {
        $viewer = $this->makeUser();
        $friend = $this->makeUser();
        $stranger = $this->makeUser();

        $this->befriend($viewer, $friend);

        $publicByFriend = $this->makePost($friend, ['body' => 'friend-public', 'visibility' => FeedPost::VISIBILITY_PUBLIC]);
        $privateByFriend = $this->makePost($friend, ['body' => 'friend-friends', 'visibility' => FeedPost::VISIBILITY_FRIENDS]);
        $publicByStranger = $this->makePost($stranger, ['body' => 'stranger-public', 'visibility' => FeedPost::VISIBILITY_PUBLIC]);

        $legacy = $this->feedBodies($viewer, 'all', false);
        $unified = $this->feedBodies($viewer, 'all', true);

        $this->assertEqualsCanonicalizing($legacy, $unified, 'all tab must return same posts in both paths');
        $this->assertContains($publicByFriend->body, $legacy);
        $this->assertContains($publicByStranger->body, $legacy);
        $this->assertNotContains($privateByFriend->body, $legacy);
    }

    // -------------------------------------------------------------------------
    // 1b. tab=friends — viewer's + friends' posts (both visibilities), both paths
    // -------------------------------------------------------------------------

    public function test_friends_tab_legacy_and_unified_show_same_posts(): void
    {
        $viewer = $this->makeUser();
        $friend = $this->makeUser();
        $stranger = $this->makeUser();

        $this->befriend($viewer, $friend);

        $this->makePost($viewer, ['body' => 'viewer-own']);
        $this->makePost($friend, ['body' => 'friend-public', 'visibility' => FeedPost::VISIBILITY_PUBLIC]);
        $this->makePost($friend, ['body' => 'friend-friends', 'visibility' => FeedPost::VISIBILITY_FRIENDS]);
        $this->makePost($stranger, ['body' => 'stranger-public', 'visibility' => FeedPost::VISIBILITY_PUBLIC]);

        $legacy = $this->feedBodies($viewer, 'friends', false);
        $unified = $this->feedBodies($viewer, 'friends', true);

        $this->assertEqualsCanonicalizing($legacy, $unified, 'friends tab must return same posts in both paths');
        $this->assertContains('viewer-own', $legacy);
        $this->assertContains('friend-public', $legacy);
        $this->assertContains('friend-friends', $legacy);
        $this->assertNotContains('stranger-public', $legacy);
    }

    // -------------------------------------------------------------------------
    // 1c. tab=mine — only viewer's own posts, both paths
    // -------------------------------------------------------------------------

    public function test_mine_tab_legacy_and_unified_show_same_posts(): void
    {
        $viewer = $this->makeUser();
        $other = $this->makeUser();

        $this->makePost($viewer, ['body' => 'viewer-post']);
        $this->makePost($other, ['body' => 'other-post']);

        $legacy = $this->feedBodies($viewer, 'mine', false);
        $unified = $this->feedBodies($viewer, 'mine', true);

        $this->assertEqualsCanonicalizing($legacy, $unified, 'mine tab must return same posts in both paths');
        $this->assertContains('viewer-post', $unified);
        $this->assertNotContains('other-post', $unified);
    }

    // -------------------------------------------------------------------------
    // 1d. Expired posts hidden in unified feed
    // -------------------------------------------------------------------------

    public function test_expired_posts_are_hidden_in_unified_feed(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();
        $this->makePost($user, ['body' => 'visible-post']);
        $this->makePost($user, ['body' => 'expired-post', 'expires_at' => now()->subHour()]);

        $bodies = $this->feedBodies($user, 'mine', true);

        $this->assertContains('visible-post', $bodies);
        $this->assertNotContains('expired-post', $bodies);
    }

    // -------------------------------------------------------------------------
    // 1e. Soft-deleted posts hidden in unified feed
    // -------------------------------------------------------------------------

    public function test_deleted_posts_are_hidden_in_unified_feed(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();
        $live = $this->makePost($user, ['body' => 'live-post']);
        $dead = $this->makePost($user, ['body' => 'deleted-post']);
        $dead->delete();

        $bodies = $this->feedBodies($user, 'mine', true);

        $this->assertContains('live-post', $bodies);
        $this->assertNotContains('deleted-post', $bodies);
    }

    // -------------------------------------------------------------------------
    // 1f. Whisper appears in all tab under unified path
    // -------------------------------------------------------------------------

    public function test_whisper_appears_in_all_tab_in_unified_feed(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $author = $this->makeUser();
        $viewer = $this->makeUser();

        $this->makePost($author, ['body' => 'whisper-body', 'is_whisper' => true]);

        $bodies = $this->feedBodies($viewer, 'all', true);

        $this->assertContains('whisper-body', $bodies);
    }

    // =========================================================================
    // Part 2 — Bookmark state in unified feed path
    // =========================================================================

    // -------------------------------------------------------------------------
    // 2a. Saved post is detected as bookmarked in unified feed
    // -------------------------------------------------------------------------

    public function test_saved_post_appears_bookmarked_in_unified_feed(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();
        $post = $this->makePost($user, ['body' => 'my-post']);

        // Create bookmark via controller (sets bookmarkable_key)
        $this->actingAs($user)->postJson(route('bookmarks.store'), [
            'bookmarkable_type' => 'feed_post',
            'bookmarkable_id' => $post->id,
        ])->assertCreated();

        $bookmarkIds = Livewire::actingAs($user)
            ->test(Feed::class, ['tab' => 'mine'])
            ->viewData('bookmarkIds');

        $this->assertArrayHasKey($post->id, $bookmarkIds);
    }

    // -------------------------------------------------------------------------
    // 2b. Unsaved post does not appear bookmarked
    // -------------------------------------------------------------------------

    public function test_unsaved_post_appears_unbookmarked_in_unified_feed(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();
        $post = $this->makePost($user, ['body' => 'unbookmarked-post']);

        $bookmarkIds = Livewire::actingAs($user)
            ->test(Feed::class, ['tab' => 'mine'])
            ->viewData('bookmarkIds');

        $this->assertArrayNotHasKey($post->id, $bookmarkIds);
    }

    // -------------------------------------------------------------------------
    // 2c. Legacy bookmark (null bookmarkable_key) still resolved in unified feed
    // -------------------------------------------------------------------------

    public function test_legacy_null_key_bookmark_detected_in_unified_feed(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();
        $post = $this->makePost($user, ['body' => 'legacy-bookmarked-post']);

        // Insert pre-migration style bookmark with no bookmarkable_key
        $bookmarkId = DB::table('bookmarks')->insertGetId([
            'user_id' => $user->id,
            'bookmarkable_type' => FeedPost::class,
            'bookmarkable_id' => $post->id,
            'bookmarkable_key' => null,
            'snapshot_body' => $post->body,
            'snapshot_is_whisper' => false,
            'snapshot_posted_at' => now(),
            'original_deleted' => false,
            'access_revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Feed loads bookmarkIds via whereIn('bookmarkable_id', ...) — must still find legacy row
        $bookmarkIds = Livewire::actingAs($user)
            ->test(Feed::class, ['tab' => 'mine'])
            ->viewData('bookmarkIds');

        $this->assertArrayHasKey($post->id, $bookmarkIds);
        $this->assertSame($bookmarkId, $bookmarkIds[$post->id]);
    }

    // -------------------------------------------------------------------------
    // 2d. Key-based bookmark (bookmarkable_key set) detected in unified feed
    // -------------------------------------------------------------------------

    public function test_key_based_bookmark_detected_in_unified_feed(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();
        $post = $this->makePost($user, ['body' => 'key-bookmarked-post']);

        $response = $this->actingAs($user)->postJson(route('bookmarks.store'), [
            'bookmarkable_type' => 'feed_post',
            'bookmarkable_id' => $post->id,
        ])->assertCreated();

        $createdBookmarkId = $response->json('id');

        $bookmarkIds = Livewire::actingAs($user)
            ->test(Feed::class, ['tab' => 'mine'])
            ->viewData('bookmarkIds');

        $this->assertArrayHasKey($post->id, $bookmarkIds);
        $this->assertSame($createdBookmarkId, $bookmarkIds[$post->id]);
    }

    // =========================================================================
    // Part 3 — Pagination / load more
    // =========================================================================

    // -------------------------------------------------------------------------
    // 3a. First page returns pageSize (25) items under unified feed
    // -------------------------------------------------------------------------

    public function test_first_page_returns_25_items_in_unified_feed(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();

        for ($i = 0; $i < 30; $i++) {
            $this->makePost($user);
        }

        $posts = Livewire::actingAs($user)
            ->test(Feed::class, ['tab' => 'mine'])
            ->viewData('posts');

        $this->assertCount(25, $posts->items());
    }

    // -------------------------------------------------------------------------
    // 3b. loadMoreFeed() advances cursor; next render shows remaining items
    // -------------------------------------------------------------------------

    public function test_load_more_feed_advances_to_next_page(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();

        for ($i = 0; $i < 30; $i++) {
            $this->makePost($user);
        }

        $component = Livewire::actingAs($user)->test(Feed::class, ['tab' => 'mine']);

        // First render: 25 items, nextFeedCursor populated
        $this->assertCount(25, $component->viewData('posts')->items());
        $this->assertNotNull($component->get('nextFeedCursor'));

        // Advance cursor
        $component->call('loadMoreFeed');

        // Second render (triggered by loadMoreFeed): remaining 5 items
        $this->assertCount(5, $component->viewData('posts')->items());
    }

    // -------------------------------------------------------------------------
    // 3c. No duplicates between page 1 and page 2 in unified feed
    // -------------------------------------------------------------------------

    public function test_no_duplicates_between_pages_in_unified_feed(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();

        for ($i = 0; $i < 30; $i++) {
            $this->makePost($user);
        }

        $component = Livewire::actingAs($user)->test(Feed::class, ['tab' => 'mine']);

        $page1Ids = collect($component->viewData('posts')->items())->pluck('id');

        $component->call('loadMoreFeed');

        $page2Ids = collect($component->viewData('posts')->items())->pluck('id');

        $this->assertEmpty($page1Ids->intersect($page2Ids), 'No post should appear on both pages');
    }

    // -------------------------------------------------------------------------
    // 3d. setTab resets feedCursor and nextFeedCursor
    // -------------------------------------------------------------------------

    public function test_set_tab_resets_cursor_state(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $user = $this->makeUser();

        for ($i = 0; $i < 30; $i++) {
            $this->makePost($user);
        }

        $component = Livewire::actingAs($user)->test(Feed::class, ['tab' => 'mine']);

        // Advance to page 2 — feedCursor becomes non-null
        $component->call('loadMoreFeed');
        $this->assertNotNull($component->get('feedCursor'), 'feedCursor should be set after loadMoreFeed');
        $this->assertCount(5, $component->viewData('posts')->items());

        // setTab must reset feedCursor so we're back at page 1 of the new tab
        $component->call('setTab', 'all');

        $this->assertNull($component->get('feedCursor'), 'setTab must reset feedCursor to null');
        // After setTab, render shows page 1 (30 posts exist on all tab → 25 items)
        $this->assertCount(25, $component->viewData('posts')->items());
    }

    // =========================================================================
    // Part 4 — Backfill idempotency
    // =========================================================================

    // -------------------------------------------------------------------------
    // 4a. feed:backfill-items exits successfully and counts match
    // -------------------------------------------------------------------------

    public function test_backfill_command_succeeds_and_counts_match(): void
    {
        $user = $this->makeUser();

        $this->makePost($user);
        $this->makePost($user);
        $this->makePost($user);

        $this->artisan('feed:backfill-items')
            ->assertExitCode(0);

        $feedPostCount = FeedPost::withTrashed()->count();
        $feedItemCount = FeedItem::withTrashed()
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->count();

        $this->assertSame($feedPostCount, $feedItemCount);
    }

    // -------------------------------------------------------------------------
    // 4b. Running backfill twice is idempotent (no duplicates)
    // -------------------------------------------------------------------------

    public function test_backfill_is_idempotent(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->makePost($user);
        }

        $this->artisan('feed:backfill-items')->assertExitCode(0);
        $this->artisan('feed:backfill-items')->assertExitCode(0);

        $feedPostCount = FeedPost::withTrashed()->count();
        $feedItemCount = FeedItem::withTrashed()
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->count();

        $this->assertSame($feedPostCount, $feedItemCount, 'Running backfill twice must not create duplicate feed_items');
    }

    // =========================================================================
    // Part 5 — Confirmations
    // =========================================================================

    // -------------------------------------------------------------------------
    // 5a. Default config flag is false (never enabled by default)
    // -------------------------------------------------------------------------

    public function test_default_feature_flag_is_false(): void
    {
        // Ensure we read the framework-resolved config, not an overridden value.
        // This verifies the config/features.php default, not .env.
        $this->assertFalse(
            config('features.unified_feed_items_enabled'),
            'features.unified_feed_items_enabled must default to false'
        );
    }

    // -------------------------------------------------------------------------
    // 5b. Community source types produce no feed_items (stubbed)
    // -------------------------------------------------------------------------

    public function test_community_source_types_produce_no_feed_items(): void
    {
        $communityTypes = [
            FeedItem::SOURCE_COMMUNITY_POST,
            FeedItem::SOURCE_COMMUNITY,
            FeedItem::SOURCE_COMMUNITY_TOPIC,
            FeedItem::SOURCE_COMMUNITY_MEMBER,
        ];

        foreach ($communityTypes as $type) {
            $count = FeedItem::withTrashed()->where('source_type', $type)->count();
            $this->assertSame(0, $count, "No feed_items of type '{$type}' should exist");
        }
    }
}
