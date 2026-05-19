<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Models\User;
use App\Services\FeedItemProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedItemProjectionTest extends TestCase
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

    // -------------------------------------------------------------------------
    // 1. Creating a feed_post produces a feed_item
    // -------------------------------------------------------------------------

    public function test_creating_feed_post_creates_feed_item(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $item = FeedItem::query()
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->where('source_id', (string) $post->id)
            ->where('item_type', FeedItem::ITEM_FEED_POST_CREATED)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame($user->id, $item->actor_id);
        $this->assertSame(FeedItem::SCOPE_PUBLIC, $item->visibility_scope);
        $this->assertNull($item->deleted_at);
    }

    // -------------------------------------------------------------------------
    // 2. Soft-deleting a feed_post soft-deletes the corresponding feed_item
    // -------------------------------------------------------------------------

    public function test_soft_deleting_feed_post_soft_deletes_feed_item(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $this->assertDatabaseCount('feed_items', 1);

        $post->delete();

        $item = FeedItem::withTrashed()
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->where('source_id', (string) $post->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertNotNull($item->deleted_at);
    }

    // -------------------------------------------------------------------------
    // 3. Whisper post produces feed_item with actor_id = null
    // -------------------------------------------------------------------------

    public function test_whisper_post_creates_feed_item_with_null_actor(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user, ['is_whisper' => true]);

        $item = FeedItem::query()
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->where('source_id', (string) $post->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertNull($item->actor_id);
        $this->assertSame(FeedItem::SCOPE_PUBLIC, $item->visibility_scope);
    }

    // -------------------------------------------------------------------------
    // 4. Backfilled whisper post keeps actor_id = null and visibility_scope = public
    // -------------------------------------------------------------------------

    public function test_backfill_whisper_keeps_null_actor_and_public_scope(): void
    {
        // Insert a whisper post via raw DB to bypass the booted() hook,
        // so we can test the backfill path independently.
        DB::table('feed_posts')->insert([
            'user_id' => null,
            'body' => 'whisper via backfill',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'is_whisper' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $postId = (int) DB::getPdo()->lastInsertId();

        // Clear any items that might exist from other test setup.
        FeedItem::query()->withTrashed()->forceDelete();

        $projector = new FeedItemProjector;
        $count = $projector->backfillFeedPosts();

        $this->assertSame(1, $count);

        $item = FeedItem::query()
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->where('source_id', (string) $postId)
            ->first();

        $this->assertNotNull($item);
        $this->assertNull($item->actor_id);
        $this->assertSame(FeedItem::SCOPE_PUBLIC, $item->visibility_scope);
    }

    // -------------------------------------------------------------------------
    // 5. Backfill is idempotent — running twice produces no duplicates
    // -------------------------------------------------------------------------

    public function test_backfill_is_idempotent(): void
    {
        $user = $this->makeUser();
        $this->makePost($user);
        $this->makePost($user);
        $this->makePost($user);

        // booted() already created 3 items on insert; run backfill on top
        $projector = new FeedItemProjector;

        $firstCount = $projector->backfillFeedPosts();
        $itemsAfterFirst = FeedItem::withTrashed()->count();

        $secondCount = $projector->backfillFeedPosts();
        $itemsAfterSecond = FeedItem::withTrashed()->count();

        $this->assertSame(3, $firstCount);
        $this->assertSame(3, $secondCount);
        $this->assertSame($itemsAfterFirst, $itemsAfterSecond);
    }

    // -------------------------------------------------------------------------
    // 6. Duplicate observer call / retry does not create duplicate feed_items
    // -------------------------------------------------------------------------

    public function test_duplicate_projection_does_not_create_duplicate_feed_items(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $projector = new FeedItemProjector;

        // Simulate retries — should be idempotent
        $projector->projectFeedPostCreated($post);
        $projector->projectFeedPostCreated($post);

        $count = FeedItem::withTrashed()
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->where('source_id', (string) $post->id)
            ->where('item_type', FeedItem::ITEM_FEED_POST_CREATED)
            ->count();

        $this->assertSame(1, $count);
    }
}
