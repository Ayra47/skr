<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['feed_post_id', 'user_id', 'parent_id', 'body', 'deleted_at'])]
class FeedComment extends Model
{
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'feed_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(FeedCommentVote::class);
    }

    public function edits(): HasMany
    {
        return $this->hasMany(FeedCommentEdit::class)->latest('created_at');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
