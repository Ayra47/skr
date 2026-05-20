<?php

namespace App\Services;

use App\Models\CommunityPost;
use App\Models\FeedItem;
use App\Models\FeedPost;
use Illuminate\Support\Carbon;

final readonly class FeedCard
{
    public const TYPE_FEED_POST = FeedItem::SOURCE_FEED_POST;

    public const TYPE_COMMUNITY_POST = FeedItem::SOURCE_COMMUNITY_POST;

    public function __construct(
        public string $type,
        public string $sourceId,
        public ?int $actorId,
        public Carbon $sortAt,
        public ?FeedPost $feedPost = null,
        public ?CommunityPost $communityPost = null,
        public ?string $communityName = null,
        public ?string $communityVisibility = null,
        public ?string $topicName = null,
        public ?string $authorDisplayName = null,
    ) {}

    public static function forFeedPost(FeedItem $item, FeedPost $post): self
    {
        return new self(
            type: self::TYPE_FEED_POST,
            sourceId: (string) $post->id,
            actorId: $item->actor_id,
            sortAt: $item->sort_at,
            feedPost: $post,
        );
    }

    public static function forCommunityPost(
        FeedItem $item,
        CommunityPost $post,
        ?string $authorDisplayName = null,
    ): self {
        return new self(
            type: self::TYPE_COMMUNITY_POST,
            sourceId: (string) $post->id,
            actorId: $item->actor_id,
            sortAt: $item->sort_at,
            communityPost: $post,
            communityName: $post->community?->name,
            communityVisibility: $post->community?->visibility,
            topicName: $post->topic?->name,
            authorDisplayName: $authorDisplayName,
        );
    }

    public function wireKey(): string
    {
        return $this->type.'-'.$this->sourceId;
    }

    public function isFeedPost(): bool
    {
        return $this->type === self::TYPE_FEED_POST && $this->feedPost !== null;
    }

    public function isCommunityPost(): bool
    {
        return $this->type === self::TYPE_COMMUNITY_POST && $this->communityPost !== null;
    }
}
