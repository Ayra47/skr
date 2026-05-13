<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['type', 'title', 'avatar', 'user_a_id', 'user_b_id'])]
class Conversation extends Model
{
    public const TYPE_DIRECT = 'direct';

    public const TYPE_GROUP = 'group';

    protected $attributes = [
        'type' => self::TYPE_DIRECT,
    ];

    public static function findOrCreateBetween(int $userAId, int $userBId): self
    {
        $a = min($userAId, $userBId);
        $b = max($userAId, $userBId);

        return self::firstOrCreate([
            'type' => self::TYPE_DIRECT,
            'user_a_id' => $a,
            'user_b_id' => $b,
        ]);
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

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(ConversationInvite::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(ConversationJoinRequest::class);
    }

    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    public function otherParticipant(int $currentUserId): User
    {
        return $this->user_a_id === $currentUserId ? $this->userB : $this->userA;
    }

    public function hasParticipant(int $userId): bool
    {
        if ($this->isGroup()) {
            return $this->hasMember($userId);
        }

        return $this->user_a_id === $userId || $this->user_b_id === $userId;
    }

    public function hasMember(int $userId): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains('user_id', $userId);
        }

        return $this->members()->where('user_id', $userId)->exists();
    }

    public function roleFor(int $userId): ?string
    {
        $member = $this->relationLoaded('members')
            ? $this->members->firstWhere('user_id', $userId)
            : $this->members()->where('user_id', $userId)->first();

        return $member?->role;
    }

    public function canManageMembers(int $userId): bool
    {
        return in_array($this->roleFor($userId), [
            ConversationMember::ROLE_OWNER,
            ConversationMember::ROLE_ADMIN,
        ], strict: true);
    }

    public function isOwner(int $userId): bool
    {
        return $this->roleFor($userId) === ConversationMember::ROLE_OWNER;
    }

    /**
     * @return Collection<int, User>
     */
    public function recipientsFor(int $senderId): Collection
    {
        if ($this->isGroup()) {
            return $this->participants()
                ->where('users.id', '!=', $senderId)
                ->get();
        }

        return new Collection([$this->otherParticipant($senderId)]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $query) use ($userId) {
            $query->where(function (Builder $query) use ($userId) {
                $query->where('type', self::TYPE_DIRECT)
                    ->where(function (Builder $query) use ($userId) {
                        $query->where('user_a_id', $userId)
                            ->orWhere('user_b_id', $userId);
                    });
            })->orWhere(function (Builder $query) use ($userId) {
                $query->where('type', self::TYPE_GROUP)
                    ->whereHas('members', fn (Builder $query) => $query->where('user_id', $userId));
            });
        });
    }
}
