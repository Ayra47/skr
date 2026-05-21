<?php

namespace App\Console\Commands;

use App\Models\CommunityPost;
use App\Models\FeedItem;
use App\Services\FeedItemProjector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('feed:reconcile-community-posts {--chunk=100 : Records per chunk} {--force : Run even when FEATURE_COMMUNITY_FEED_ITEMS is false}')]
#[Description('Reconcile community_posts into feed_items projection table')]
class ReconcileCommunityPostFeedItems extends Command
{
    public function handle(FeedItemProjector $projector): int
    {
        $force = (bool) $this->option('force');

        if (! config('features.community_feed_items_enabled') && ! $force) {
            $this->info('Community feed items are disabled. Use --force to reconcile anyway.');

            return self::SUCCESS;
        }

        if ($force) {
            config(['features.community_feed_items_enabled' => true]);
        }

        $projected = 0;
        $removed = 0;
        $chunk = max(1, (int) $this->option('chunk'));

        CommunityPost::withTrashed()
            ->with('community')
            ->orderBy('id')
            ->chunkById($chunk, function ($posts) use ($projector, &$projected, &$removed): void {
                foreach ($posts as $post) {
                    if ($this->isEligible($post)) {
                        $projector->projectCommunityPostCreated($post);
                        $projected++;

                        continue;
                    }

                    $projector->deleteForCommunityPost($post);
                    $removed++;
                }
            });

        FeedItem::query()
            ->where('source_type', FeedItem::SOURCE_COMMUNITY_POST)
            ->whereDoesntHave('actor')
            ->update(['deleted_at' => now()]);

        $this->info("Projected {$projected} community post(s); removed {$removed} ineligible projection(s).");

        return self::SUCCESS;
    }

    private function isEligible(CommunityPost $post): bool
    {
        if ($post->trashed() || $post->isExpired()) {
            return false;
        }

        if ($post->moderation_status !== CommunityPost::MODERATION_VISIBLE) {
            return false;
        }

        return (bool) $post->community?->allow_posts_in_member_feed;
    }
}
