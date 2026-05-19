<?php

namespace App\Services;

use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Models\FeedVote;
use App\Models\User;
use Illuminate\Support\Collection;

final class ProfileActivityReader
{
    public function __construct(private FeedVisibilityService $visibilityService) {}

    /**
     * Return up to $limit FeedPost objects from feed_items for the profile user's activity,
     * filtered by viewer visibility (surface='profile_activity' excludes whispers).
     *
     * Returns the same shape as the legacy feedPosts() query so the profile template
     * does not require changes.
     *
     * @return Collection<int, FeedPost>
     */
    public function readForProfile(User $viewer, User $profileUser, int $limit = 5): Collection
    {
        $items = FeedItem::query()
            ->forProfileActivity()
            ->where('actor_id', $profileUser->id)
            ->where('source_type', FeedItem::SOURCE_FEED_POST)
            ->orderByDesc('sort_at')
            ->orderByDesc('id')
            ->limit($limit * 4)
            ->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $postIds = $items->pluck('source_id')->map(fn ($id) => (int) $id)->all();
        $posts = FeedPost::withTrashed()->whereIn('id', $postIds)->get();
        $this->visibilityService->preloadFeedPosts($posts->all());

        $visibleIds = $items
            ->filter(fn (FeedItem $item) => $this->visibilityService->canViewerSeeFeedItem($viewer, $item, 'profile_activity'))
            ->take($limit)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($visibleIds)) {
            return collect();
        }

        $ordered = array_flip($visibleIds);

        return FeedPost::query()
            ->with([
                'author',
                'attachments' => fn ($q) => $q->orderBy('position'),
            ])
            ->withCount([
                'comments',
                'votes as up_votes_count' => fn ($q) => $q->where('value', FeedVote::VALUE_UP),
                'votes as down_votes_count' => fn ($q) => $q->where('value', FeedVote::VALUE_DOWN),
            ])
            ->whereIn('id', $visibleIds)
            ->get()
            ->sortBy(fn (FeedPost $post) => $ordered[$post->id] ?? PHP_INT_MAX)
            ->values();
    }
}
