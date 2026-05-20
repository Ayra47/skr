<?php

namespace App\Services;

use App\Models\CommunityPost;
use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class FeedItemsReader
{
    public function __construct(private FeedVisibilityService $visibilityService) {}

    /**
     * Read one page of feed items visible to $viewer on the given tab.
     *
     * Strategy: fetch raw items in batches of $rawBatch, run each through
     * FeedVisibilityService one by one, stop when $pageSize visible items are
     * collected or $maxScan raw items have been examined. The cursor encodes the
     * position of the last raw item scanned, so the next request continues from
     * exactly where scanning stopped — even if that item was not visible.
     *
     * tab='all'     → visibility_scope = public  (mirrors legacy FeedPost::forTab 'all')
     * tab='mine'    → actor_id = viewer
     * tab='friends' → actor_id = viewer OR actor_id IN friendIds
     *
     * @param  'friends'|'all'|'mine'  $tab
     */
    public function readForFeed(
        User $viewer,
        string $tab,
        int $pageSize = 25,
        ?string $cursor = null,
        int $rawBatch = 100,
        int $maxScan = 500,
    ): FeedPage {
        $friendIds = $viewer->friendIds();

        /** @var Collection<int, FeedItem> $visible */
        $visible = collect();
        $lastScannedItem = null;
        $scanned = 0;
        $batchCursor = $cursor;
        $done = false;
        $dbExhausted = false;

        while (! $done && ! $dbExhausted && $visible->count() < $pageSize && $scanned < $maxScan) {
            $limit = min($rawBatch, $maxScan - $scanned);
            $batch = $this->fetchBatch($viewer, $tab, $friendIds, $batchCursor, $limit);

            if ($batch->isEmpty()) {
                $dbExhausted = true;
                break;
            }

            $this->preloadSources($batch);

            foreach ($batch as $item) {
                $lastScannedItem = $item;
                $scanned++;

                if ($this->visibilityService->canViewerSeeFeedItem($viewer, $item, 'feed')) {
                    $visible->push($item);

                    if ($visible->count() === $pageSize) {
                        $done = true;
                        break;
                    }
                }
            }

            if (! $done) {
                if ($batch->count() < $limit) {
                    $dbExhausted = true;
                } else {
                    /** @var FeedItem $lastInBatch */
                    $lastInBatch = $batch->last();
                    $batchCursor = $this->encodeCursor($lastInBatch->sort_at, $lastInBatch->id);
                }
            }
        }

        $nextCursor = null;

        if ($lastScannedItem !== null && $visible->count() === $pageSize) {
            // Peek one item beyond lastScannedItem to confirm a next page exists.
            // This avoids returning a dangling cursor when the page happens to land
            // exactly on the last item in the DB.
            $peekCursor = $this->encodeCursor($lastScannedItem->sort_at, $lastScannedItem->id);
            $peek = $this->fetchBatch($viewer, $tab, $friendIds, $peekCursor, 1);

            if ($peek->isNotEmpty()) {
                $nextCursor = $peekCursor;
            }
        }

        return new FeedPage($visible->values(), $nextCursor);
    }

    /**
     * @param  array<int>  $friendIds
     * @return Collection<int, FeedItem>
     */
    private function fetchBatch(
        User $viewer,
        string $tab,
        array $friendIds,
        ?string $cursor,
        int $limit,
    ): Collection {
        $query = FeedItem::query()
            ->where('show_in_feed', true)
            ->whereNull('deleted_at');

        if (! config('features.community_feed_items_enabled')) {
            $query->where('source_type', '!=', FeedItem::SOURCE_COMMUNITY_POST);
        }

        match ($tab) {
            'all' => $query->where('visibility_scope', FeedItem::SCOPE_PUBLIC),
            'mine' => $query->where('actor_id', $viewer->id),
            default => $query->where(function ($q) use ($viewer, $friendIds): void {
                $q->where('actor_id', $viewer->id)
                    ->orWhereIn('actor_id', $friendIds);

                if (config('features.community_feed_items_enabled')) {
                    $q->orWhere('source_type', FeedItem::SOURCE_COMMUNITY_POST);
                }
            }),
        };

        if ($cursor !== null) {
            $decoded = $this->decodeCursor($cursor);

            if ($decoded !== null) {
                $query->where(function ($q) use ($decoded): void {
                    $q->where('sort_at', '<', $decoded['sort_at'])
                        ->orWhere(function ($q2) use ($decoded): void {
                            $q2->where('sort_at', $decoded['sort_at'])
                                ->where('id', '<', $decoded['id']);
                        });
                });
            }
        }

        return $query
            ->orderByDesc('sort_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    private function preloadSources(Collection $items): void
    {
        $this->preloadFeedPosts($items);
        $this->preloadCommunityPosts($items);
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    private function preloadFeedPosts(Collection $items): void
    {
        $ids = $items
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($ids)) {
            return;
        }

        $posts = FeedPost::withTrashed()->whereIn('id', $ids)->get();
        $this->visibilityService->preloadFeedPosts($posts->all());
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    private function preloadCommunityPosts(Collection $items): void
    {
        if (! config('features.community_feed_items_enabled')) {
            return;
        }

        $ids = $items
            ->where('source_type', FeedItem::SOURCE_COMMUNITY_POST)
            ->pluck('source_id')
            ->all();

        if (empty($ids)) {
            return;
        }

        $posts = CommunityPost::withTrashed()
            ->with('community')
            ->whereIn('id', $ids)
            ->get();
        $this->visibilityService->preloadCommunityPosts($posts->all());
    }

    private function encodeCursor(Carbon $sortAt, int $id): string
    {
        return base64_encode((string) json_encode([
            'sort_at' => $sortAt->toIso8601String(),
            'id' => $id,
        ]));
    }

    /**
     * @return array{sort_at: string, id: int}|null
     */
    private function decodeCursor(string $cursor): ?array
    {
        $data = json_decode((string) base64_decode($cursor), true);

        if (! is_array($data) || ! isset($data['sort_at'], $data['id'])) {
            return null;
        }

        return ['sort_at' => $data['sort_at'], 'id' => (int) $data['id']];
    }
}
