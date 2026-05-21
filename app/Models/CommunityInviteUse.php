<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invite_id', 'community_id', 'user_id', 'used_at', 'ip_hash', 'user_agent_hash'])]
class CommunityInviteUse extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    public function invite(): BelongsTo
    {
        return $this->belongsTo(CommunityInvite::class, 'invite_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
