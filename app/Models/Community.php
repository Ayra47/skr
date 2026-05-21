<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'slug', 'description', 'avatar_path', 'cover_path',
    'join_mode', 'visibility', 'created_by', 'member_count', 'post_count',
    'member_limit', 'default_post_ttl_seconds', 'allow_posts_in_member_feed',
    'invite_policy', 'posting_policy', 'hide_real_names',
    'show_key_fingerprints', 'anonymous_reactions_enabled',
])]
class Community extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const JOIN_OPEN = 'open';

    public const JOIN_INVITE_ONLY = 'invite_only';

    public const JOIN_REQUEST = 'request';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_HIDDEN = 'hidden';

    public const INVITE_POLICY_ALL_MEMBERS = 'all_members';

    public const INVITE_POLICY_MODERATORS_ONLY = 'moderators_only';

    public const POSTING_POLICY_EVERYONE = 'everyone';

    public const POSTING_POLICY_MODERATORS_ONLY = 'moderators_only';

    public const ALLOWED_MEMBER_LIMITS = [5, 50, 100, 500, 1000, 5000];

    public const ALLOWED_TTL_SECONDS = [3600, 86400, 604800];

    protected function casts(): array
    {
        return [
            'allow_posts_in_member_feed' => 'boolean',
            'hide_real_names' => 'boolean',
            'show_key_fingerprints' => 'boolean',
            'anonymous_reactions_enabled' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(CommunityTopic::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(CommunityInvite::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(CommunityJoinRequest::class);
    }

    public function keyEpochs(): HasMany
    {
        return $this->hasMany(CommunityKeyEpoch::class);
    }

    public function auditLog(): HasMany
    {
        return $this->hasMany(CommunityAuditLog::class);
    }
}
