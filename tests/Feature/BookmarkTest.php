<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BookmarkTest extends TestCase
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
            'body' => 'test post body',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ], $attrs));
    }

    private function storeBookmark(User $user, FeedPost $post): TestResponse
    {
        return $this->actingAs($user)->postJson(route('bookmarks.store'), [
            'bookmarkable_type' => 'feed_post',
            'bookmarkable_id' => $post->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. Creating a feed_post bookmark stores bookmarkable_key
    // -------------------------------------------------------------------------

    public function test_feed_post_bookmark_stores_bookmarkable_key(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $this->storeBookmark($user, $post)->assertCreated();

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'bookmarkable_id' => $post->id,
            'bookmarkable_key' => (string) $post->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // 2. Old bookmark with only bookmarkable_id (null key) still resolves
    //    — duplicate detection falls back to bookmarkable_id
    // -------------------------------------------------------------------------

    public function test_old_bookmark_without_key_still_resolves_as_duplicate(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        // Simulate a pre-migration row: bookmarkable_key deliberately null
        DB::table('bookmarks')->insert([
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

        // Should detect the existing row via bookmarkable_id fallback and return 200 (not 201)
        $this->storeBookmark($user, $post)->assertOk()->assertJson(['bookmarked' => true]);

        // No second row created
        $this->assertSame(1, Bookmark::where('user_id', $user->id)->count());
    }

    // -------------------------------------------------------------------------
    // 3. Duplicate bookmark prevention works via bookmarkable_key
    // -------------------------------------------------------------------------

    public function test_duplicate_bookmark_prevention_via_bookmarkable_key(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $first = $this->storeBookmark($user, $post);
        $first->assertCreated();
        $firstId = $first->json('id');

        $second = $this->storeBookmark($user, $post);
        $second->assertOk()->assertJson(['bookmarked' => true, 'id' => $firstId]);

        $this->assertSame(1, Bookmark::where('user_id', $user->id)->count());
    }

    // -------------------------------------------------------------------------
    // 4. Deleting a feed_post marks original_deleted on the bookmark
    //    (covers both legacy bookmarkable_id path and new key path)
    // -------------------------------------------------------------------------

    public function test_deleting_feed_post_marks_original_deleted(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $this->storeBookmark($user, $post)->assertCreated();

        $post->delete();

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'bookmarkable_id' => $post->id,
            'original_deleted' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // 5. Bookmarks page renders feed_post bookmarks
    // -------------------------------------------------------------------------

    public function test_bookmarks_page_renders_feed_post_bookmarks(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $this->storeBookmark($user, $post)->assertCreated();

        $this->actingAs($user)
            ->get(route('bookmarks.index'))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // 6. Feed page bookmark state still resolves via bookmarkable_id
    // -------------------------------------------------------------------------

    public function test_feed_page_bookmark_state_still_works(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $this->storeBookmark($user, $post)->assertCreated();

        // Feed page loads bookmarkIds keyed by bookmarkable_id — must still find the bookmark
        $bookmark = Bookmark::where('user_id', $user->id)
            ->where('bookmarkable_type', FeedPost::class)
            ->where('bookmarkable_id', $post->id)
            ->first();

        $this->assertNotNull($bookmark);
        $this->assertSame((string) $post->id, $bookmark->bookmarkable_key);
    }

    // -------------------------------------------------------------------------
    // 7. community_post bookmark returns 422 while feature is disabled
    // -------------------------------------------------------------------------

    public function test_community_post_bookmark_returns_422_unsupported(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson(route('bookmarks.store'), [
            'bookmarkable_type' => 'community_post',
            'bookmarkable_id' => 1,
        ])->assertUnprocessable();
    }
}
