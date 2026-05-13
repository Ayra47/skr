<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'conversation_id', 'sender_id', 'reply_to_id',
    'encrypted_payload', 'delivered_at', 'read_at',
    'expires_at', 'edited_at', 'deleted_at', 'deleted_for',
])]
class Message extends Model
{
    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'expires_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'deleted_for' => 'array',
        ];
    }

    public function isDeletedForUser(int $userId): bool
    {
        return $this->deleted_at !== null
            || in_array($userId, $this->deleted_for ?? [], strict: true);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(MessageEdit::class)->orderBy('created_at');
    }

    public function pins(): HasMany
    {
        return $this->hasMany(PinnedMessage::class);
    }

    public function scopeUndelivered(Builder $query): Builder
    {
        return $query->whereNull('delivered_at');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeVisibleTo(Builder $query, int $userId): Builder
    {
        return $query
            ->whereNull('deleted_at')
            ->where(function (Builder $q) use ($userId) {
                $q->whereNull('deleted_for')
                    ->orWhereJsonDoesntContain('deleted_for', $userId);
            });
    }
}
