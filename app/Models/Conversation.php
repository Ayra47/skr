<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_a_id', 'user_b_id'])]
class Conversation extends Model
{
    public static function findOrCreateBetween(int $userAId, int $userBId): self
    {
        $a = min($userAId, $userBId);
        $b = max($userAId, $userBId);

        return self::firstOrCreate(['user_a_id' => $a, 'user_b_id' => $b]);
    }

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function otherParticipant(int $currentUserId): User
    {
        return $this->user_a_id === $currentUserId ? $this->userB : $this->userA;
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->user_a_id === $userId || $this->user_b_id === $userId;
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
    }
}
