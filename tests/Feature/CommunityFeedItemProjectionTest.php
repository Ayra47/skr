<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\FeedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityFeedItemProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_feed_items_feature_flag_defaults_to_false(): void
    {
        $this->assertStringContainsString(
            "env('FEATURE_COMMUNITY_FEED_ITEMS', false)",
            file_get_contents(config_path('features.php')),
            'features.community_feed_items_enabled must default to false in config/features.php'
        );

        $this->assertStringContainsString(
            'FEATURE_COMMUNITY_FEED_ITEMS=false',
            file_get_contents(base_path('.env.example')),
            'FEATURE_COMMUNITY_FEED_ITEMS must default to false in .env.example'
        );
    }

    public function test_flag_false_does_not_create_community_post_feed_item(): void
    {
        config(['features.community_feed_items_enabled' => false]);

        $post = $this->createCommunityPost();

        $this->assertNull($this->feedItemFor($post));
    }

    public function test_flag_true_creates_feed_item_for_eligible_public_community_post(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_PUBLIC],
            ['visibility' => Community::VISIBILITY_PUBLIC],
        );

        $item = $this->feedItemFor($post);

        $this->assertNotNull($item);
        $this->assertSame(FeedItem::SOURCE_COMMUNITY_POST, $item->source_type);
        $this->assertSame(FeedItem::ITEM_COMMUNITY_POST_CREATED, $item->item_type);
        $this->assertSame($post->user_id, $item->actor_id);
        $this->assertSame($post->community_id, $item->community_id);
        $this->assertSame($post->topic_id, $item->topic_id);
        $this->assertSame($post->id, $item->post_id);
        $this->assertSame(FeedItem::SCOPE_PUBLIC, $item->visibility_scope);
        $this->assertFalse($item->show_in_profile_activity);
        $this->assertNull($item->deleted_at);
    }

    public function test_allow_posts_in_member_feed_false_prevents_feed_item(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_PUBLIC],
            ['allow_posts_in_member_feed' => false],
        );

        $this->assertNull($this->feedItemFor($post));
    }

    public function test_private_community_post_creates_community_members_scoped_feed_item(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost(
            ['visibility' => CommunityPost::VISIBILITY_PUBLIC],
            ['visibility' => Community::VISIBILITY_PRIVATE],
        );

        $item = $this->feedItemFor($post);

        $this->assertNotNull($item);
        $this->assertSame(FeedItem::SCOPE_COMMUNITY_MEMBERS_ONLY, $item->visibility_scope);
    }

    public function test_hidden_moderation_post_does_not_create_active_feed_item(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost([
            'moderation_status' => CommunityPost::MODERATION_HIDDEN,
        ]);

        $this->assertNull($this->feedItemFor($post));
    }

    public function test_expired_post_does_not_create_active_feed_item(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost([
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertNull($this->feedItemFor($post));
    }

    public function test_soft_deleted_community_post_soft_deletes_feed_item(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);

        $this->assertNotNull($this->feedItemFor($post));

        $post->delete();

        $item = $this->feedItemFor($post, withTrashed: true);

        $this->assertNotNull($item);
        $this->assertNotNull($item->deleted_at);
    }

    public function test_moderation_status_visible_to_hidden_soft_deletes_feed_item(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);

        $post->update(['moderation_status' => CommunityPost::MODERATION_HIDDEN]);

        $item = $this->feedItemFor($post, withTrashed: true);

        $this->assertNotNull($item);
        $this->assertNotNull($item->deleted_at);
    }

    public function test_moderation_status_hidden_to_visible_reprojects_feed_item_if_eligible(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost([
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
            'moderation_status' => CommunityPost::MODERATION_HIDDEN,
        ]);

        $this->assertNull($this->feedItemFor($post));

        $post->update(['moderation_status' => CommunityPost::MODERATION_VISIBLE]);

        $item = $this->feedItemFor($post);

        $this->assertNotNull($item);
        $this->assertNull($item->deleted_at);
    }

    public function test_feed_item_does_not_contain_ciphertext(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost([
            'ciphertext' => 'ciphertext-secret-batch-11a',
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ]);

        $item = $this->feedItemFor($post);

        $this->assertNotNull($item);
        $this->assertStringNotContainsString('ciphertext-secret-batch-11a', (string) json_encode($item->getAttributes()));
    }

    public function test_feed_item_does_not_contain_nonce(): void
    {
        config(['features.community_feed_items_enabled' => true]);

        $post = $this->createCommunityPost([
            'nonce' => 'nonce-secret-batch-11a',
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ]);

        $item = $this->feedItemFor($post);

        $this->assertNotNull($item);
        $this->assertStringNotContainsString('nonce-secret-batch-11a', (string) json_encode($item->getAttributes()));
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

    private function feedItemFor(CommunityPost $post, bool $withTrashed = false): ?FeedItem
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
