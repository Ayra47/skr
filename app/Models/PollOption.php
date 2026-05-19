<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['poll_id', 'text', 'position', 'votes_count'])]
class PollOption extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
            'position' => 'integer',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class, 'option_id');
    }

    public function percentage(int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round($this->votes_count / $total * 100, 1);
    }
}
