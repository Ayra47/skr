<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'bio',
    'accent_color',
    'show_shared_chats',
    'show_shared_groups',
    'profile_access',
    'online_status_visibility',
    'shared_friends_count_visibility',
    'feed_posts_count_visibility',
    'profile_posts_visibility',
    'avatar_visibility',
    'profile_communities_visibility',
    'community_activity_visibility',
    'community_posts_profile_visibility',
    'community_posts_feed_visibility',
    'joined_communities_activity_visibility',
    'community_roles_visibility',
])]
class ProfileSetting extends Model
{
    public const AUDIENCE_NONE = 'none';

    public const AUDIENCE_FRIENDS = 'friends';

    public const AUDIENCE_EVERYONE = 'everyone';

    /**
     * @return array<int, string>
     */
    public static function audienceValues(): array
    {
        return [self::AUDIENCE_NONE, self::AUDIENCE_FRIENDS, self::AUDIENCE_EVERYONE];
    }

    protected function casts(): array
    {
        return [
            'show_shared_chats' => 'boolean',
            'show_shared_groups' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
