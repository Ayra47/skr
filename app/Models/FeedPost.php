<?php

namespace App\Models;

use App\Services\FeedItemProjector;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

#[Fillable([
    'user_id', 'body', 'visibility', 'is_whisper',
    'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    'expires_at',
])]
class FeedPost extends Model
{
    use SoftDeletes;

    public const VISIBILITY_FRIENDS = 'friends';

    public const VISIBILITY_PUBLIC = 'public';

    public const EXPIRES_1H = '1h';

    public const EXPIRES_24H = '24h';

    public const EXPIRES_7D = '7d';

    public const EXPIRES_FOREVER = 'forever';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_whisper' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function visibilityValues(): array
    {
        return [self::VISIBILITY_FRIENDS, self::VISIBILITY_PUBLIC];
    }

    /**
     * @return array<int, string>
     */
    public static function expirationValues(): array
    {
        return [self::EXPIRES_1H, self::EXPIRES_24H, self::EXPIRES_7D, self::EXPIRES_FOREVER];
    }

    protected static function booted(): void
    {
        static::saving(function (FeedPost $post): void {
            if (! $post->is_whisper) {
                return;
            }

            $post->visibility = self::VISIBILITY_PUBLIC;
            $post->user_id = null;
        });

        static::created(function (FeedPost $post): void {
            app(FeedItemProjector::class)->projectFeedPostCreated($post);
        });

        static::deleted(function (FeedPost $post): void {
            Bookmark::query()
                ->where('bookmarkable_type', self::class)
                ->where('bookmarkable_id', $post->id)
                ->update(['original_deleted' => true]);

            app(FeedItemProjector::class)->deleteForSource(
                FeedItem::SOURCE_FEED_POST,
                (string) $post->id,
            );
        });
    }

    public static function expiresAtFor(string $expiresIn): ?Carbon
    {
        return match ($expiresIn) {
            self::EXPIRES_1H => now()->addHour(),
            self::EXPIRES_24H => now()->addDay(),
            self::EXPIRES_7D => now()->addDays(7),
            self::EXPIRES_FOREVER => null,
            default => throw new InvalidArgumentException('Unsupported feed post expiration option.'),
        };
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(FeedVote::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FeedPostAttachment::class)->orderBy('position');
    }

    public function poll(): HasOne
    {
        return $this->hasOne(Poll::class, 'feed_post_id');
    }

    /**
     * @param  array<int>|null  $friendIds
     */
    public function scopeVisibleTo(Builder $query, User $user, ?array $friendIds = null): Builder
    {
        $friendIds ??= $user->friendIds();

        return $query->where(function (Builder $query) use ($user, $friendIds) {
            $query->where('visibility', self::VISIBILITY_PUBLIC)
                ->orWhere('user_id', $user->id)
                ->orWhere(function (Builder $query) use ($friendIds) {
                    $query->where('visibility', self::VISIBILITY_FRIENDS)
                        ->whereIn('user_id', $friendIds);
                });
        });
    }

    /**
     * @param  array<int>|null  $friendIds
     */
    public function scopeForTab(Builder $query, User $user, string $tab, ?array $friendIds = null): Builder
    {
        $friendIds ??= $user->friendIds();

        return match ($tab) {
            'all' => $query->where('visibility', self::VISIBILITY_PUBLIC),
            'mine' => $query->where('user_id', $user->id),
            default => $query->where(function (Builder $query) use ($user, $friendIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('user_id', $friendIds);
            })->whereIn('visibility', self::visibilityValues()),
        };
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeVisibleOnProfile(Builder $query): Builder
    {
        return $query->where('is_whisper', false);
    }

    /**
     * @param  array<int>|null  $friendIds
     */
    public function isVisibleTo(User $user, ?array $friendIds = null): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        if ($this->visibility === self::VISIBILITY_PUBLIC || $this->user_id === $user->id) {
            return true;
        }

        $friendIds ??= $user->friendIds();

        return $this->visibility === self::VISIBILITY_FRIENDS
            && in_array($this->user_id, $friendIds, strict: true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasAttachment(): bool
    {
        return $this->relationLoaded('attachments')
            ? $this->attachments->isNotEmpty()
            : $this->attachments()->exists();
    }
}
