<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['community_id', 'epoch_number', 'reason'])]
class CommunityKeyEpoch extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    public const REASON_INITIAL = 'initial';

    public const REASON_MEMBER_LEFT = 'member_left';

    public const REASON_MEMBER_REMOVED = 'member_removed';

    public const REASON_PERIODIC = 'periodic';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function memberKeys(): HasMany
    {
        return $this->hasMany(CommunityMemberKey::class, 'epoch_id');
    }
}
