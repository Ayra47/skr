<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['community_id', 'created_by', 'code', 'max_uses', 'use_count', 'is_revoked', 'revoked_at', 'expires_at'])]
class CommunityInvite extends Model
{
    use HasFactory;
    use HasUuids;

    protected function casts(): array
    {
        return [
            'is_revoked' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null || $this->is_revoked || $this->isExpired()) {
            return false;
        }

        return $this->max_uses === null || $this->use_count < $this->max_uses;
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function uses(): HasMany
    {
        return $this->hasMany(CommunityInviteUse::class, 'invite_id');
    }
}
