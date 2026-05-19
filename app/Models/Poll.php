<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['feed_post_id', 'mode', 'max_choices', 'closes_at', 'secret'])]
class Poll extends Model
{
    use SoftDeletes;

    public const MODE_SINGLE = 'single';

    public const MODE_MULTIPLE = 'multiple';

    protected function casts(): array
    {
        return [
            'closes_at' => 'datetime',
            'max_choices' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'feed_post_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function isExpired(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }

    public function totalVotes(): int
    {
        return (int) $this->options->sum('votes_count');
    }

    public function voterHashFor(int $userId): string
    {
        return hash_hmac('sha256', (string) $userId, $this->secret);
    }

    public function hasVoted(int $userId): bool
    {
        $hash = $this->voterHashFor($userId);

        return $this->votes()->where('voter_hash', $hash)->exists();
    }

    /**
     * @return array<int>
     */
    public function votedOptionIds(int $userId): array
    {
        $hash = $this->voterHashFor($userId);

        return $this->votes()
            ->where('voter_hash', $hash)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
