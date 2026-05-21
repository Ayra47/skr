<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityDirectInvite;
use App\Models\CommunityFile;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\FeedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityLifecycleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_posts_soft_deletes_post_feed_item_and_bookmark_without_payload_leakage(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $user = User::factory()->create();
        $post = $this->communityPost($user, [
            'ciphertext' => 'EXPIRE-CIPHERTEXT',
            'nonce' => 'EXPIRE-NONCE',
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ], [
            'name' => 'Expire Bookmark Community',
            'visibility' => Community::VISIBILITY_PUBLIC,
        ]);
        $this->actingAs($user)->postJson(route('bookmarks.store'), [
            'bookmarkable_type' => 'community_post',
            'bookmarkable_id' => $post->id,
        ])->assertCreated();

        $post->update(['expires_at' => now()->subMinute()]);

        $this->artisan('communities:expire-posts')->assertSuccessful();
        $this->artisan('communities:expire-posts')->assertSuccessful();

        $this->assertSoftDeleted('community_posts', ['id' => $post->id]);
        $this->assertNotNull($this->communityFeedItem($post, withTrashed: true)?->deleted_at);
        $this->assertDatabaseHas('bookmarks', [
            'bookmarkable_type' => CommunityPost::class,
            'bookmarkable_key' => $post->id,
            'original_deleted' => true,
        ]);

        $this->actingAs($user)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSeeText('Community post unavailable')
            ->assertDontSee('EXPIRE-CIPHERTEXT')
            ->assertDontSee('EXPIRE-NONCE');
    }

    public function test_reconcile_community_posts_recreates_missing_feed_item_and_removes_ineligible_items(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $eligible = $this->communityPost(User::factory()->create(), [
            'ciphertext' => 'RECONCILE-CIPHERTEXT',
            'nonce' => 'RECONCILE-NONCE',
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ], [
            'visibility' => Community::VISIBILITY_PUBLIC,
        ]);
        $this->communityFeedItem($eligible)?->forceDelete();

        $ineligible = $this->communityPost(User::factory()->create(), [], [
            'allow_posts_in_member_feed' => true,
        ]);
        $ineligible->community->update(['allow_posts_in_member_feed' => false]);

        $this->artisan('feed:reconcile-community-posts')->assertSuccessful();
        $this->artisan('feed:reconcile-community-posts')->assertSuccessful();

        $eligibleItem = $this->communityFeedItem($eligible);
        $this->assertNotNull($eligibleItem);
        $this->assertStringNotContainsString('RECONCILE-CIPHERTEXT', (string) json_encode($eligibleItem->getAttributes()));
        $this->assertStringNotContainsString('RECONCILE-NONCE', (string) json_encode($eligibleItem->getAttributes()));
        $this->assertNotNull($this->communityFeedItem($ineligible, withTrashed: true)?->deleted_at);
    }

    public function test_cleanup_file_blobs_removes_deleted_blobs_and_marks_missing_blobs(): void
    {
        Storage::fake('local');

        $active = CommunityFile::factory()->create(['storage_key' => 'community-files/active.enc']);
        Storage::disk('local')->put($active->storage_key, 'active');

        $deleted = CommunityFile::factory()->create(['storage_key' => 'community-files/deleted.enc']);
        Storage::disk('local')->put($deleted->storage_key, 'deleted');
        $deleted->delete();

        $missing = CommunityFile::factory()->create(['storage_key' => 'community-files/missing.enc']);
        $missing->delete();

        $this->artisan('communities:cleanup-file-blobs')->assertSuccessful();
        $this->artisan('communities:cleanup-file-blobs')->assertSuccessful();

        Storage::disk('local')->assertExists($active->storage_key);
        Storage::disk('local')->assertMissing($deleted->storage_key);
        $this->assertNotNull(CommunityFile::withTrashed()->findOrFail($deleted->id)->blob_deleted_at);
        $this->assertNotNull(CommunityFile::withTrashed()->findOrFail($missing->id)->blob_deleted_at);
    }

    public function test_expire_direct_invites_marks_only_expired_pending_invites(): void
    {
        $expired = CommunityDirectInvite::factory()->pending()->create(['expires_at' => now()->subMinute()]);
        $pending = CommunityDirectInvite::factory()->pending()->create(['expires_at' => now()->addMinute()]);
        $accepted = CommunityDirectInvite::factory()->accepted()->create(['expires_at' => now()->subMinute()]);

        $this->artisan('communities:expire-direct-invites')->assertSuccessful();
        $this->artisan('communities:expire-direct-invites')->assertSuccessful();

        $this->assertSame(CommunityDirectInvite::STATUS_EXPIRED, $expired->fresh()->status);
        $this->assertNotNull($expired->fresh()->responded_at);
        $this->assertSame(CommunityDirectInvite::STATUS_PENDING, $pending->fresh()->status);
        $this->assertSame(CommunityDirectInvite::STATUS_ACCEPTED, $accepted->fresh()->status);
    }

    public function test_reconcile_counters_fixes_member_community_and_topic_counts(): void
    {
        $community = Community::factory()->create(['member_count' => 99, 'post_count' => 99]);
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 99]);
        CommunityMember::factory()->count(2)->for($community)->create();
        CommunityMember::factory()->for($community)->create(['status' => CommunityMember::STATUS_LEFT]);
        CommunityPost::factory()->for($community)->for($topic, 'topic')->create();
        CommunityPost::factory()->for($community)->for($topic, 'topic')->create(['moderation_status' => CommunityPost::MODERATION_HIDDEN]);
        CommunityPost::factory()->for($community)->for($topic, 'topic')->create(['expires_at' => now()->subMinute()]);

        $this->artisan('communities:reconcile-counters')->assertSuccessful();
        $this->artisan('communities:reconcile-counters')->assertSuccessful();

        $this->assertSame(2, $community->fresh()->member_count);
        $this->assertSame(1, $community->fresh()->post_count);
        $this->assertSame(1, $topic->fresh()->post_count);
    }

    private function communityPost(User $author, array $postAttrs = [], array $communityAttrs = []): CommunityPost
    {
        $community = Community::factory()->create(array_merge([
            'allow_posts_in_member_feed' => true,
            'visibility' => Community::VISIBILITY_PUBLIC,
        ], $communityAttrs));
        $topic = CommunityTopic::factory()->for($community)->create();
        CommunityMember::factory()->for($community)->for($author)->create();

        return CommunityPost::factory()
            ->for($community)
            ->for($topic, 'topic')
            ->for($author, 'author')
            ->create($postAttrs);
    }

    private function communityFeedItem(CommunityPost $post, bool $withTrashed = false): ?FeedItem
    {
        $query = FeedItem::query()
            ->where('source_type', FeedItem::SOURCE_COMMUNITY_POST)
            ->where('source_id', $post->id)
            ->where('item_type', FeedItem::ITEM_COMMUNITY_POST_CREATED);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->first();
    }
}
