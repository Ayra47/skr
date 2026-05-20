<?php

namespace App\Services;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Models\ProfileSetting;
use App\Models\User;

/**
 * Determines whether a viewer can see a given FeedItem on a specific surface.
 *
 * Surface values: 'feed' | 'profile_activity' | 'bookmark'
 *
 * This is the canonical access-control layer for feed_items.
 * SQL prefilters (show_in_feed, visibility_scope) only narrow candidates;
 * this service makes the final decision using live data.
 */
final class FeedVisibilityService
{
    /** @var array<string, FeedPost> */
    private array $feedPostCache = [];

    /** @var array<string, CommunityPost> */
    private array $communityPostCache = [];

    /**
     * Preload FeedPosts to avoid N+1 queries across a batch of feed items.
     *
     * @param  FeedPost[]  $posts
     */
    public function preloadFeedPosts(array $posts): void
    {
        foreach ($posts as $post) {
            $this->feedPostCache[(string) $post->id] = $post;
        }
    }

    /**
     * Preload CommunityPosts to avoid N+1 queries across a batch of feed items.
     *
     * @param  CommunityPost[]  $posts
     */
    public function preloadCommunityPosts(array $posts): void
    {
        foreach ($posts as $post) {
            $this->communityPostCache[(string) $post->id] = $post;
        }
    }

    public function canViewerSeeFeedItem(User $viewer, FeedItem $item, string $surface = 'feed'): bool
    {
        if ($item->deleted_at !== null) {
            return false;
        }

        return match ($item->source_type) {
            FeedItem::SOURCE_FEED_POST => $this->canSeeFeedPost($viewer, $item, $surface),
            FeedItem::SOURCE_COMMUNITY_POST => $this->canSeeCommunityPost($viewer, $item),
            FeedItem::SOURCE_COMMUNITY,
            FeedItem::SOURCE_COMMUNITY_TOPIC,
            FeedItem::SOURCE_COMMUNITY_MEMBER => $this->canSeeCommunityItem($viewer, $item, $surface),
            default => false,
        };
    }

    private function canSeeFeedPost(User $viewer, FeedItem $item, string $surface): bool
    {
        $post = $this->feedPostCache[$item->source_id]
            ?? FeedPost::withTrashed()->find((int) $item->source_id);

        if (! $post || $post->trashed() || $post->isExpired()) {
            return false;
        }

        if ($surface === 'profile_activity' && $post->is_whisper) {
            return false;
        }

        return $post->isVisibleTo($viewer);
    }

    private function canSeeCommunityPost(User $viewer, FeedItem $item): bool
    {
        $post = $this->communityPostCache[$item->source_id]
            ?? CommunityPost::withTrashed()->with('community')->find($item->source_id);

        if (! $post || $post->trashed() || $post->isExpired()) {
            return false;
        }

        if ($post->moderation_status !== CommunityPost::MODERATION_VISIBLE) {
            return false;
        }

        $community = $post->relationLoaded('community')
            ? $post->community
            : $post->community()->first();

        if (! $community || ! $community->allow_posts_in_member_feed) {
            return false;
        }

        if (
            $community->visibility === Community::VISIBILITY_PUBLIC
            && $post->visibility === CommunityPost::VISIBILITY_PUBLIC
        ) {
            return true;
        }

        return $this->isActiveMember($viewer->id, $community->id);
    }

    private function canSeeCommunityItem(User $viewer, FeedItem $item, string $surface): bool
    {
        return false;
    }

    private function audienceAllows(string $audience, User $viewer, User $owner): bool
    {
        return match ($audience) {
            ProfileSetting::AUDIENCE_EVERYONE => true,
            ProfileSetting::AUDIENCE_FRIENDS => $viewer->isFriendWith($owner->id),
            default => false,
        };
    }

    private function isActiveMember(int $userId, string $communityId): bool
    {
        return CommunityMember::query()
            ->where('community_id', $communityId)
            ->where('user_id', $userId)
            ->where('status', CommunityMember::STATUS_ACTIVE)
            ->exists();
    }
}
