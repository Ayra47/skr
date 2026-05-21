<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_id', 'user_id', 'notifications_enabled', 'muted', 'unread_posts_count', 'last_visited_at', 'pinned', 'last_read_community_seq', 'last_activity_seen_at'])]
class CommunityUserState extends Model
{
    use HasFactory;

    protected $table = 'community_user_state';

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'muted' => 'boolean',
            'pinned' => 'boolean',
            'last_visited_at' => 'datetime',
            'last_activity_seen_at' => 'datetime',
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
