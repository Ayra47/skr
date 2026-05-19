<?php

namespace App\Services;

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

    public function deleteForSource(string $sourceType, string $sourceId): void
    {
        FeedItem::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->update(['deleted_at' => now()]);
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
}
