<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FeedAttachmentThumbnail
{
    public function store(UploadedFile $attachment): ?string
    {
        if (! str_starts_with((string) $attachment->getMimeType(), 'image/')) {
            return null;
        }

        $path = 'feed-attachment-thumbnails/'.Str::uuid().'.webp';
        $encoded = (new ImageManager(new GdDriver))
            ->decode($attachment->getRealPath())
            ->cover(400, 400)
            ->encode(new WebpEncoder(80));

        Storage::disk('local')->put($path, (string) $encoded);

        return $path;
    }
}
