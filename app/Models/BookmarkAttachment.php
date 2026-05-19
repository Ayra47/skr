<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['bookmark_id', 'path', 'thumbnail_path', 'name', 'mime', 'size', 'position'])]
class BookmarkAttachment extends Model
{
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'position' => 'integer',
        ];
    }

    public function bookmark(): BelongsTo
    {
        return $this->belongsTo(Bookmark::class);
    }

    public function isImage(): bool
    {
        return is_string($this->mime) && str_starts_with($this->mime, 'image/');
    }

    public function isVideo(): bool
    {
        return is_string($this->mime) && str_starts_with($this->mime, 'video/');
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk('local')->exists($this->path);
    }

    public function downloadName(): string
    {
        return filled($this->name) ? $this->name : 'attachment';
    }
}
