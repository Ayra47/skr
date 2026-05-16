<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['login', 'name', 'password', 'pseudonym', 'email', 'email_verified_at', 'pending_email', 'pending_password_hash', 'avatar', 'backup_code_hash', 'two_factor_enabled', 'two_factor_code', 'two_factor_code_expires_at', 'last_seen_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_code_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friends', 'user_id', 'friend_id')
            ->withTimestamps();
    }

    public function friendOf(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friends', 'friend_id', 'user_id')
            ->withTimestamps();
    }

    public function allFriends(): BelongsToMany
    {
        return $this->friends()->union($this->friendOf());
    }

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'sender_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'receiver_id');
    }

    public function friendCodes(): HasMany
    {
        return $this->hasMany(FriendCode::class);
    }

    public function activeFriendCode(): ?FriendCode
    {
        return $this->friendCodes()->active()->latest()->first();
    }

    public function unreadFriendRequestsCount(): int
    {
        return $this->receivedFriendRequests()->unread()->pending()->count();
    }

    public function userKey(): HasOne
    {
        return $this->hasOne(UserKey::class);
    }

    public function profileSetting(): HasOne
    {
        return $this->hasOne(ProfileSetting::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_a_id');
    }

    public function groupConversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function feedPosts(): HasMany
    {
        return $this->hasMany(FeedPost::class);
    }

    public function feedVotes(): HasMany
    {
        return $this->hasMany(FeedVote::class);
    }

    public function feedComments(): HasMany
    {
        return $this->hasMany(FeedComment::class);
    }

    public function feedName(): string
    {
        return $this->pseudonym ?: 'anon-'.$this->id;
    }

    /**
     * @return array<int>
     */
    public function friendIds(): array
    {
        return $this->friends()
            ->pluck('friend_id')
            ->merge($this->friendOf()->pluck('user_id'))
            ->unique()
            ->values()
            ->all();
    }

    public function isFriendWith(int $userId): bool
    {
        return $this->friends()->where('friend_id', $userId)->exists()
            || $this->friendOf()->where('user_id', $userId)->exists();
    }

    public function sharesGroupWith(int $userId): bool
    {
        return ConversationMember::query()
            ->where('user_id', $this->id)
            ->whereHas('conversation.members', fn ($query) => $query->where('user_id', $userId))
            ->exists();
    }
}
