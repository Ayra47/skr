<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id', 'bookmarkable_type', 'bookmarkable_id', 'bookmarkable_key',
    'community_id', 'access_revoked',
    'snapshot_body', 'snapshot_author_id', 'snapshot_author_name',
    'snapshot_is_whisper', 'snapshot_posted_at', 'source_label', 'original_deleted',
])]
class Bookmark extends Model
{
    protected function casts(): array
    {
        return [
            'snapshot_is_whisper' => 'boolean',
            'snapshot_posted_at' => 'datetime',
            'original_deleted' => 'boolean',
            'access_revoked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BookmarkAttachment::class)->orderBy('position');
    }
}
