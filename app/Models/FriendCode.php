<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'code', 'is_used', 'is_blocked', 'expires_at', 'used_at'])]
class FriendCode extends Model
{
    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'is_blocked' => 'boolean',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function friendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class);
    }

    public function isActive(): bool
    {
        return ! $this->is_blocked && ! $this->is_used && $this->expires_at->gt(now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->lte(now());
    }

    public function scopeActive($query)
    {
        return $query->where('is_blocked', false)
            ->where('is_used', false)
            ->where('expires_at', '>', now());
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function generateCode(): string
    {
        return str_pad(random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
    }
}
