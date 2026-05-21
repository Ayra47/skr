<?php

namespace App\Console\Commands;

use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Services\FeedItemProjector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('feed:backfill-items {--chunk=500 : Records per chunk}')]
#[Description('Backfill feed_posts into feed_items projection table (idempotent)')]
class BackfillFeedItems extends Command
{
    public function handle(FeedItemProjector $projector): int
    {
        $feedPostCount = FeedPost::withTrashed()->count();
        $this->info("Backfilling {$feedPostCount} feed_posts into feed_items...");

        $chunk = (int) $this->option('chunk');
        $count = $projector->backfillFeedPosts($chunk);

        $this->info("Done. Projected {$count} feed_post rows.");

        $feedItemCount = FeedItem::withTrashed()->where('source_type', FeedItem::SOURCE_FEED_POST)->count();
        $this->line("Verification: feed_posts={$feedPostCount}, feed_items(source=feed_post)={$feedItemCount}");

        if ($feedPostCount !== $feedItemCount) {
            $this->warn('Count mismatch — some posts may have been skipped or are duplicated. Re-run to fix.');

            return self::FAILURE;
        }

        $this->info('Counts match. Backfill complete.');

        return self::SUCCESS;
    }
}
