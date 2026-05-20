<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'community_id', 'post_id', 'uploaded_by',
    'path', 'thumbnail_path', 'name', 'mime', 'size', 'position',
    'storage_key', 'encrypted_filename', 'mime_bucket', 'size_bytes',
    'checksum_sha256', 'key_epoch_id', 'expires_at', 'blob_deleted_at',
])]
class CommunityFile extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'blob_deleted_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function keyEpoch(): BelongsTo
    {
        return $this->belongsTo(CommunityKeyEpoch::class, 'key_epoch_id');
    }
}
