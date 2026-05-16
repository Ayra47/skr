<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'public_key_jwk', 'storage_preference', 'key_backup', 'key_change_source', 'key_changed_at', 'notify_sound', 'notify_email', 'notify_email_text', 'notify_push', 'notify_push_text'])]
class UserKey extends Model
{
    protected function casts(): array
    {
        return [
            'storage_preference' => 'string',
            'key_changed_at' => 'datetime',
            'notify_sound' => 'boolean',
            'notify_email' => 'boolean',
            'notify_email_text' => 'boolean',
            'notify_push' => 'boolean',
            'notify_push_text' => 'boolean',
        ];
    }

    public function fingerprint(): ?string
    {
        if (! $this->public_key_jwk) {
            return null;
        }

        $hex = hash('sha256', $this->public_key_jwk);

        return strtoupper(implode(' ', str_split(substr($hex, 0, 12), 4)));
    }

    public function shouldWarnPartners(): bool
    {
        return $this->key_change_source === 'fresh'
            && $this->key_changed_at !== null
            && $this->key_changed_at->diffInDays(now()) <= 7;
    }

    public function daysAgoChanged(): int
    {
        return (int) $this->key_changed_at?->diffInDays(now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
