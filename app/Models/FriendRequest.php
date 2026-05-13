<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sender_id', 'receiver_id', 'friend_code_id', 'status', 'is_read'])]
class FriendRequest extends Model
{
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function friendCode(): BelongsTo
    {
        return $this->belongsTo(FriendCode::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForReceiver($query, $userId)
    {
        return $query->where('receiver_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
