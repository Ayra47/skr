<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'actor_id', 'item_type', 'source_type', 'source_id',
    'community_id', 'topic_id', 'post_id',
    'visibility_scope', 'show_in_feed', 'show_in_profile_activity', 'sort_at',
])]
class FeedItem extends Model
{
    use SoftDeletes;

    public const ITEM_FEED_POST_CREATED = 'feed_post_created';

    public const ITEM_COMMUNITY_POST_CREATED = 'community_post_created';

    public const ITEM_COMMUNITY_CREATED = 'community_created';

    public const ITEM_COMMUNITY_JOINED = 'community_joined';

    public const ITEM_COMMUNITY_ROLE_CHANGED = 'community_role_changed';

    public const ITEM_COMMUNITY_TOPIC_CREATED = 'community_topic_created';

    public const SOURCE_FEED_POST = 'feed_post';

    public const SOURCE_COMMUNITY_POST = 'community_post';

    public const SOURCE_COMMUNITY = 'community';

    public const SOURCE_COMMUNITY_TOPIC = 'community_topic';

    public const SOURCE_COMMUNITY_MEMBER = 'community_member';

    public const SCOPE_PUBLIC = 'public';

    public const SCOPE_FRIENDS = 'friends';

    public const SCOPE_COMMUNITY_MEMBERS_ONLY = 'community_members_only';

    public const SCOPE_PRIVATE = 'private';

    protected function casts(): array
    {
        return [
            'show_in_feed' => 'boolean',
            'show_in_profile_activity' => 'boolean',
            'sort_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  Builder<FeedItem>  $query
     * @return Builder<FeedItem>
     */
    public function scopeForFeed(Builder $query): Builder
    {
        return $query->where('show_in_feed', true)->whereNull('deleted_at');
    }

    /**
     * @param  Builder<FeedItem>  $query
     * @return Builder<FeedItem>
     */
    public function scopeForProfileActivity(Builder $query): Builder
    {
        return $query->where('show_in_profile_activity', true)->whereNull('deleted_at');
    }
}
