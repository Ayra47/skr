<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'component_id', 'kind', 'status', 'body', 'duration_minutes', 'started_at', 'resolved_at'])]
class StatusIncident extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function formattedDuration(): ?string
    {
        if (! $this->duration_minutes) {
            return null;
        }

        $h = intdiv($this->duration_minutes, 60);
        $m = $this->duration_minutes % 60;

        if ($h > 0 && $m > 0) {
            return "{$h} ч {$m} мин";
        }

        if ($h > 0) {
            return "{$h} ч";
        }

        return "{$m} мин";
    }
}
