<?php

namespace App\Services;

use App\Models\Community;
use App\Models\CommunityPost;
use App\Models\FeedItem;
use App\Models\FeedPost;
use Illuminate\Support\Facades\DB;

final class FeedItemProjector
{
    public function projectFeedPostCreated(FeedPost $post): FeedItem
    {
        return FeedItem::query()->updateOrCreate(
            [
                'source_type' => FeedItem::SOURCE_FEED_POST,
                'source_id' => (string) $post->id,
                'item_type' => FeedItem::ITEM_FEED_POST_CREATED,
            ],
            [
                'actor_id' => $post->user_id,
                'community_id' => null,
                'topic_id' => null,
                'post_id' => null,
                'visibility_scope' => $post->visibility,
                'show_in_feed' => true,
                'show_in_profile_activity' => true,
                'sort_at' => $post->created_at,
                'deleted_at' => $post->deleted_at,
            ]
        );
    }

    public function projectCommunityPostCreated(CommunityPost $post): ?FeedItem
    {
        if (! config('features.community_feed_items_enabled')) {
            return null;
        }

        $community = $post->relationLoaded('community')
            ? $post->community
            : $post->community()->first();

        if (! $community || ! $community->allow_posts_in_member_feed) {
            return null;
        }

        if ($post->moderation_status !== CommunityPost::MODERATION_VISIBLE) {
            return null;
        }

        if ($post->trashed() || $post->isExpired()) {
            return null;
        }

        return FeedItem::withTrashed()->updateOrCreate(
            [
                'source_type' => FeedItem::SOURCE_COMMUNITY_POST,
                'source_id' => (string) $post->id,
                'item_type' => FeedItem::ITEM_COMMUNITY_POST_CREATED,
            ],
            [
                'actor_id' => $post->user_id,
                'community_id' => $post->community_id,
                'topic_id' => $post->topic_id,
                'post_id' => $post->id,
                'visibility_scope' => $this->communityPostVisibilityScope($community, $post),
                'show_in_feed' => true,
                'show_in_profile_activity' => true,
                'sort_at' => $post->created_at,
                'deleted_at' => null,
            ]
        );
    }

    public function deleteForSource(string $sourceType, string $sourceId): void
    {
        FeedItem::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->update(['deleted_at' => now()]);
    }

    public function deleteForCommunityPost(CommunityPost $post): void
    {
        $this->deleteForSource(FeedItem::SOURCE_COMMUNITY_POST, (string) $post->id);
    }

    public function deleteForCommunity(string $communityId): void
    {
        FeedItem::query()
            ->where('community_id', $communityId)
            ->update(['deleted_at' => now()]);
    }

    public function deleteForTopic(string $topicId): void
    {
        FeedItem::query()
            ->where('topic_id', $topicId)
            ->update(['deleted_at' => now()]);
    }

    /**
     * Backfill feed_posts → feed_items in chunks (idempotent via upsert).
     */
    public function backfillFeedPosts(int $chunkSize = 500): int
    {
        $count = 0;

        FeedPost::query()->withTrashed()->orderBy('id')->chunkById($chunkSize, function ($posts) use (&$count) {
            $rows = $posts->map(fn (FeedPost $post) => [
                'actor_id' => $post->user_id,
                'item_type' => FeedItem::ITEM_FEED_POST_CREATED,
                'source_type' => FeedItem::SOURCE_FEED_POST,
                'source_id' => (string) $post->id,
                'community_id' => null,
                'topic_id' => null,
                'post_id' => null,
                'visibility_scope' => $post->visibility,
                'show_in_feed' => true,
                'show_in_profile_activity' => true,
                'sort_at' => $post->created_at,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => $post->deleted_at,
            ])->all();

            DB::table('feed_items')->upsert(
                $rows,
                ['source_type', 'source_id', 'item_type'],
                ['actor_id', 'visibility_scope', 'sort_at', 'deleted_at', 'updated_at']
            );

            $count += count($rows);
        });

        return $count;
    }

    private function communityPostVisibilityScope(Community $community, CommunityPost $post): string
    {
        if (
            $community->visibility === Community::VISIBILITY_PUBLIC
            && $post->visibility === CommunityPost::VISIBILITY_PUBLIC
        ) {
            return FeedItem::SCOPE_PUBLIC;
        }

        return FeedItem::SCOPE_COMMUNITY_MEMBERS_ONLY;
    }
}
