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
    'community_id', 'name', 'description', 'slug', 'type', 'posting_policy',
    'sort_order', 'created_by', 'post_count',
    'is_system', 'is_pinned', 'is_archived', 'archived_at',
])]
class CommunityTopic extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const TYPE_REGULAR = 'regular';

    public const TYPE_ANNOUNCEMENTS = 'announcements';

    public const TYPE_ARCHIVE = 'archive';

    public const POSTING_POLICY_EVERYONE = 'everyone';

    public const POSTING_POLICY_MODERATORS_ONLY = 'moderators_only';

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_pinned' => 'boolean',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class, 'topic_id');
    }
}
