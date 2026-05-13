<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationSession extends Model
{
    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_id',
        'duration_minutes',
        'last_encrypted_payload',
        'expires_at',
        'stopped_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isActive(): bool
    {
        if ($this->stopped_at !== null) {
            return false;
        }

        if ($this->duration_minutes === 0) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
