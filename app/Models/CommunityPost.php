<?php

namespace App\Models;

use App\Services\FeedItemProjector;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'community_id', 'topic_id', 'user_id', 'epoch_id',
    'body', 'ciphertext', 'nonce', 'community_seq', 'topic_seq',
    'visibility', 'is_pinned', 'reaction_count', 'comment_count',
    'reply_count', 'attachments_count', 'expires_at',
    'ttl_seconds', 'moderation_status', 'client_idempotency_key',
])]
class CommunityPost extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_MEMBERS_ONLY = 'members_only';

    public const VISIBILITY_PRIVATE = 'private';

    public const MODERATION_VISIBLE = 'visible';

    public const MODERATION_HIDDEN = 'hidden';

    public const MODERATION_DELETED_BY_MODERATOR = 'deleted_by_moderator';

    protected static function booted(): void
    {
        static::created(function (CommunityPost $post): void {
            app(FeedItemProjector::class)->projectCommunityPostCreated($post);
        });

        static::updated(function (CommunityPost $post): void {
            if (! $post->wasChanged('moderation_status')) {
                return;
            }

            if ($post->moderation_status === self::MODERATION_VISIBLE) {
                app(FeedItemProjector::class)->projectCommunityPostCreated($post);

                return;
            }

            app(FeedItemProjector::class)->deleteForCommunityPost($post);
        });

        static::deleted(function (CommunityPost $post): void {
            Bookmark::query()
                ->where('bookmarkable_type', self::class)
                ->where('bookmarkable_key', $post->id)
                ->update(['original_deleted' => true]);

            app(FeedItemProjector::class)->deleteForCommunityPost($post);
        });
    }

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isEncrypted(): bool
    {
        return blank($this->body) && filled($this->ciphertext) && filled($this->nonce);
    }

    public function isPlaintext(): bool
    {
        return filled($this->body);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CommunityTopic::class, 'topic_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function epoch(): BelongsTo
    {
        return $this->belongsTo(CommunityKeyEpoch::class, 'epoch_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(CommunityFile::class, 'post_id')->orderBy('position');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommunityPostReaction::class, 'post_id');
    }
}
