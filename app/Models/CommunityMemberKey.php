<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_id', 'epoch_id', 'user_id', 'device_key_id', 'encrypted_key'])]
class CommunityMemberKey extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function epoch(): BelongsTo
    {
        return $this->belongsTo(CommunityKeyEpoch::class, 'epoch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceKey(): BelongsTo
    {
        return $this->belongsTo(UserDeviceKey::class, 'device_key_id');
    }
}
