<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
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

    private function makeCommunityPost(User $author, array $postAttrs = [], array $communityAttrs = []): CommunityPost
    {
        $community = Community::factory()->create(array_merge([
            'allow_posts_in_member_feed' => false,
            'visibility' => Community::VISIBILITY_PRIVATE,
        ], $communityAttrs));
        $topic = CommunityTopic::factory()->for($community)->create(['name' => 'Bookmarks Topic']);
        CommunityMember::factory()->for($community)->for($author)->create();

        return CommunityPost::factory()
            ->for($community)
            ->for($topic, 'topic')
            ->for($author, 'author')
            ->create($postAttrs);
    }

    private function storeCommunityBookmark(User $user, CommunityPost $post): TestResponse
    {
        return $this->actingAs($user)->postJson(route('bookmarks.store'), [
            'bookmarkable_type' => 'community_post',
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
    // 7. community_post bookmarks
    // -------------------------------------------------------------------------

    public function test_active_member_can_bookmark_community_post(): void
    {
        $user = $this->makeUser();
        $post = $this->makeCommunityPost($user, [
            'ciphertext' => 'BOOKMARK-CIPHERTEXT',
            'nonce' => 'BOOKMARK-NONCE',
        ]);

        $this->storeCommunityBookmark($user, $post)->assertCreated();

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'bookmarkable_type' => CommunityPost::class,
            'bookmarkable_id' => null,
            'bookmarkable_key' => $post->id,
            'community_id' => $post->community_id,
            'access_revoked' => false,
            'original_deleted' => false,
            'snapshot_body' => null,
        ]);

        $bookmark = Bookmark::where('user_id', $user->id)->where('bookmarkable_key', $post->id)->firstOrFail();
        $this->assertStringNotContainsString('BOOKMARK-CIPHERTEXT', (string) json_encode($bookmark->getAttributes()));
        $this->assertStringNotContainsString('BOOKMARK-NONCE', (string) json_encode($bookmark->getAttributes()));
    }

    public function test_public_community_public_post_can_be_bookmarked_by_non_member(): void
    {
        $author = $this->makeUser();
        $viewer = $this->makeUser();
        $post = $this->makeCommunityPost($author, [
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ], [
            'name' => 'Public Bookmark Community',
            'visibility' => Community::VISIBILITY_PUBLIC,
        ]);

        $this->storeCommunityBookmark($viewer, $post)->assertCreated();
    }

    public function test_non_member_cannot_bookmark_private_community_post(): void
    {
        $author = $this->makeUser();
        $viewer = $this->makeUser();
        $post = $this->makeCommunityPost($author);

        $this->storeCommunityBookmark($viewer, $post)->assertForbidden();
    }

    public function test_expired_community_post_cannot_be_bookmarked(): void
    {
        $user = $this->makeUser();
        $post = $this->makeCommunityPost($user, ['expires_at' => now()->subMinute()]);

        $this->storeCommunityBookmark($user, $post)->assertForbidden();
    }

    public function test_moderation_hidden_community_post_cannot_be_bookmarked(): void
    {
        $user = $this->makeUser();
        $post = $this->makeCommunityPost($user, ['moderation_status' => CommunityPost::MODERATION_HIDDEN]);

        $this->storeCommunityBookmark($user, $post)->assertForbidden();
    }

    public function test_duplicate_community_post_bookmark_is_prevented_by_key(): void
    {
        $user = $this->makeUser();
        $post = $this->makeCommunityPost($user);

        $first = $this->storeCommunityBookmark($user, $post);
        $first->assertCreated();

        $this->storeCommunityBookmark($user, $post)
            ->assertOk()
            ->assertJson(['bookmarked' => true, 'id' => $first->json('id')]);

        $this->assertSame(1, Bookmark::where('user_id', $user->id)->where('bookmarkable_type', CommunityPost::class)->count());
    }

    public function test_community_post_bookmark_can_be_removed_without_deleting_source(): void
    {
        $user = $this->makeUser();
        $post = $this->makeCommunityPost($user);
        $bookmarkId = $this->storeCommunityBookmark($user, $post)->assertCreated()->json('id');

        $this->actingAs($user)->delete(route('bookmarks.destroy', $bookmarkId))->assertNoContent();

        $this->assertDatabaseMissing('bookmarks', ['id' => $bookmarkId]);
        $this->assertModelExists($post);
    }

    public function test_community_post_bookmark_renders_encrypted_placeholder_without_payloads(): void
    {
        $user = $this->makeUser();
        $post = $this->makeCommunityPost($user, [
            'ciphertext' => 'BOOKMARK-SECRET-CIPHERTEXT',
            'nonce' => 'BOOKMARK-SECRET-NONCE',
        ], [
            'name' => 'Private Bookmark Community',
        ]);

        $this->storeCommunityBookmark($user, $post)->assertCreated();

        $this->actingAs($user)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSeeText('Encrypted community post')
            ->assertSeeText('Private Bookmark Community')
            ->assertDontSee('BOOKMARK-SECRET-CIPHERTEXT')
            ->assertDontSee('BOOKMARK-SECRET-NONCE');
    }

    public function test_private_community_bookmark_locks_without_metadata_after_access_lost(): void
    {
        $user = $this->makeUser();
        $post = $this->makeCommunityPost($user, [], [
            'name' => 'Hidden After Leave Community',
            'visibility' => Community::VISIBILITY_HIDDEN,
        ]);

        $this->storeCommunityBookmark($user, $post)->assertCreated();
        CommunityMember::where('community_id', $post->community_id)
            ->where('user_id', $user->id)
            ->update(['status' => CommunityMember::STATUS_LEFT, 'left_at' => now()]);

        $this->actingAs($user)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSeeText('Community post unavailable')
            ->assertDontSeeText('Hidden After Leave Community')
            ->assertDontSeeText('Bookmarks Topic');

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'bookmarkable_type' => CommunityPost::class,
            'bookmarkable_key' => $post->id,
            'access_revoked' => true,
        ]);
    }

    public function test_deleted_community_post_bookmark_renders_unavailable_without_payloads(): void
    {
        $user = $this->makeUser();
        $post = $this->makeCommunityPost($user, [
            'ciphertext' => 'DELETED-BOOKMARK-CIPHERTEXT',
            'nonce' => 'DELETED-BOOKMARK-NONCE',
        ]);

        $this->storeCommunityBookmark($user, $post)->assertCreated();
        $post->delete();

        $this->actingAs($user)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSeeText('Community post unavailable')
            ->assertDontSee('DELETED-BOOKMARK-CIPHERTEXT')
            ->assertDontSee('DELETED-BOOKMARK-NONCE');

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'bookmarkable_type' => CommunityPost::class,
            'bookmarkable_key' => $post->id,
            'original_deleted' => true,
        ]);
    }
}
