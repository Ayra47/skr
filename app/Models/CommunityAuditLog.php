<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_id', 'actor_id', 'target_user_id', 'action', 'payload'])]
class CommunityAuditLog extends Model
{
    use HasFactory;

    protected $table = 'community_audit_log';

    public $timestamps = false;

    public const ACTION_MEMBER_ADDED = 'member_added';

    public const ACTION_MEMBER_REMOVED = 'member_removed';

    public const ACTION_MEMBER_BANNED = 'member_banned';

    public const ACTION_MEMBER_SUSPENDED = 'member_suspended';

    public const ACTION_ROLE_CHANGED = 'role_changed';

    public const ACTION_SETTINGS_UPDATED = 'settings_updated';

    public const ACTION_INVITE_CREATED = 'invite_created';

    public const ACTION_INVITE_REVOKED = 'invite_revoked';

    public const ACTION_TOPIC_CREATED = 'topic_created';

    public const ACTION_TOPIC_ARCHIVED = 'topic_archived';

    public const ACTION_POST_MODERATED = 'post_moderated';

    public const ACTION_KEY_EPOCH_ROTATED = 'key_epoch_rotated';

    public const ACTION_COMMUNITY_CREATED = 'community_created';

    public const ACTION_MEMBER_JOINED = 'member_joined';

    public const ACTION_JOIN_REQUEST_APPROVED = 'join_request_approved';

    public const ACTION_KEY_DELIVERED = 'key_delivered';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
