<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatFile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_id',
        'size_encrypted',
        'storage_path',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
