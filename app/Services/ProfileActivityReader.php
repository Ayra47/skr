<?php

namespace App\Services;

use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Models\FeedVote;
use App\Models\ProfileSetting;
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

    /**
     * Return mixed feed cards for profile activity when community feed_items are enabled.
     *
     * @return Collection<int, FeedCard>
     */
    public function readCardsForProfile(User $viewer, User $profileUser, int $limit = 5): Collection
    {
        $sourceTypes = [FeedItem::SOURCE_FEED_POST];

        if (config('features.community_feed_items_enabled')) {
            $sourceTypes[] = FeedItem::SOURCE_COMMUNITY_POST;
        }

        $items = FeedItem::query()
            ->forProfileActivity()
            ->where('actor_id', $profileUser->id)
            ->whereIn('source_type', $sourceTypes)
            ->orderByDesc('sort_at')
            ->orderByDesc('id')
            ->limit($limit * 6)
            ->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $feedPosts = FeedPost::withTrashed()
            ->with([
                'author',
                'attachments' => fn ($query) => $query->orderBy('position'),
            ])
            ->withCount([
                'comments',
                'votes as up_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_UP),
                'votes as down_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_DOWN),
            ])
            ->whereIn(
                'id',
                $items
                    ->where('source_type', FeedItem::SOURCE_FEED_POST)
                    ->pluck('source_id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all()
            )
            ->get()
            ->keyBy('id');

        $communityPosts = collect();

        if (config('features.community_feed_items_enabled')) {
            $communityPosts = CommunityPost::withTrashed()
                ->with(['author', 'community', 'topic'])
                ->whereIn(
                    'id',
                    $items
                        ->where('source_type', FeedItem::SOURCE_COMMUNITY_POST)
                        ->pluck('source_id')
                        ->values()
                        ->all()
                )
                ->get()
                ->keyBy('id');
        }

        $this->visibilityService->preloadFeedPosts($feedPosts->values()->all());
        $this->visibilityService->preloadCommunityPosts($communityPosts->values()->all());

        $communityMemberNames = CommunityMember::query()
            ->whereIn('community_id', $communityPosts->pluck('community_id')->filter()->unique()->values())
            ->whereIn('user_id', $communityPosts->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy(fn (CommunityMember $member) => $member->community_id.':'.$member->user_id);

        return $items
            ->map(function (FeedItem $item) use ($viewer, $profileUser, $feedPosts, $communityPosts, $communityMemberNames): ?FeedCard {
                if (! $this->visibilityService->canViewerSeeFeedItem($viewer, $item, 'profile_activity')) {
                    return null;
                }

                if ($item->source_type === FeedItem::SOURCE_FEED_POST) {
                    $post = $feedPosts->get((int) $item->source_id);

                    return $post ? FeedCard::forFeedPost($item, $post) : null;
                }

                if ($item->source_type === FeedItem::SOURCE_COMMUNITY_POST) {
                    if (! $this->canShowCommunityProfileActivity($viewer, $profileUser)) {
                        return null;
                    }

                    $post = $communityPosts->get($item->source_id);

                    if (! $post) {
                        return null;
                    }

                    $member = $communityMemberNames->get($post->community_id.':'.$post->user_id);
                    $authorDisplayName = $post->community?->hide_real_names
                        ? ($member?->pseudonym ?: $member?->community_display_name ?: 'member #'.$post->user_id)
                        : ($post->author?->feedName() ?: 'member #'.$post->user_id);

                    return FeedCard::forCommunityPost($item, $post, $authorDisplayName);
                }

                return null;
            })
            ->filter()
            ->take($limit)
            ->values();
    }

    private function canShowCommunityProfileActivity(User $viewer, User $profileUser): bool
    {
        if ($viewer->is($profileUser)) {
            return true;
        }

        $settings = $profileUser->profileSetting;

        return $this->audienceAllows($settings?->community_activity_visibility ?? ProfileSetting::AUDIENCE_FRIENDS, $viewer, $profileUser)
            && $this->audienceAllows($settings?->community_posts_profile_visibility ?? ProfileSetting::AUDIENCE_FRIENDS, $viewer, $profileUser);
    }

    private function audienceAllows(string $audience, User $viewer, User $profileUser): bool
    {
        return match ($audience) {
            ProfileSetting::AUDIENCE_EVERYONE => true,
            ProfileSetting::AUDIENCE_FRIENDS => $viewer->isFriendWith($profileUser->id),
            default => false,
        };
    }
}
