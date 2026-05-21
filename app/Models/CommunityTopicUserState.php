<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_id', 'topic_id', 'user_id', 'muted', 'notifications_enabled', 'unread_count', 'last_read_post_id', 'last_read_topic_seq'])]
class CommunityTopicUserState extends Model
{
    use HasFactory;

    protected $table = 'community_topic_user_state';

    protected function casts(): array
    {
        return [
            'muted' => 'boolean',
            'notifications_enabled' => 'boolean',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CommunityTopic::class, 'topic_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
