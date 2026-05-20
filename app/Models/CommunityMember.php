<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'community_id', 'user_id', 'role', 'status', 'joined_at',
    'left_at', 'banned_at', 'suspended_until', 'ban_reason_code',
    'encrypted_ban_note', 'community_display_name', 'pseudonym',
    'avatar_color', 'public_key_fingerprint', 'last_seen_at',
])]
class CommunityMember extends Model
{
    use HasFactory;

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_MEMBER = 'member';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING_KEY_DELIVERY = 'pending_key_delivery';

    public const STATUS_LEFT = 'left';

    public const STATUS_BANNED = 'banned';

    public const STATUS_SUSPENDED = 'suspended';

    public const BAN_REASON_SPAM = 'spam';

    public const BAN_REASON_HARASSMENT = 'harassment';

    public const BAN_REASON_INAPPROPRIATE_CONTENT = 'inappropriate_content';

    public const BAN_REASON_RULE_VIOLATION = 'rule_violation';

    public const BAN_REASON_OTHER = 'other';

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'banned_at' => 'datetime',
            'suspended_until' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
