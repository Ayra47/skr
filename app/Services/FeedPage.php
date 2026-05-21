<?php

namespace App\Services;

use App\Models\FeedItem;
use Illuminate\Support\Collection;

/**
 * Result of FeedItemsReader::readForFeed.
 * Contains the visible FeedItem objects for the page and the cursor for the next page.
 */
final readonly class FeedPage
{
    /**
     * @param  Collection<int, FeedItem>  $items
     */
    public function __construct(
        public Collection $items,
        public ?string $nextCursor,
    ) {}

    public function hasNextPage(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * @return list<int>
     */
    public function feedPostIds(): array
    {
        return $this->items
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
