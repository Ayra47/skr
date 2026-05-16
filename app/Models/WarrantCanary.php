<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['signature', 'is_current', 'published_at'])]
class WarrantCanary extends Model
{
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public static function makeCurrent(): self
    {
        self::where('is_current', true)->update(['is_current' => false]);

        return self::create([
            'signature' => self::generateSignature(),
            'is_current' => true,
            'published_at' => now(),
        ]);
    }

    public static function generateSignature(): string
    {
        $hex = strtoupper(bin2hex(random_bytes(8)));

        return implode(' ', str_split($hex, 4));
    }

    public function isStale(): bool
    {
        return $this->published_at->diffInDays(now()) > 8;
    }
}
