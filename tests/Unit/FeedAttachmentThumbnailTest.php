<?php

namespace Tests\Unit;

use App\Services\FeedAttachmentThumbnail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class FeedAttachmentThumbnailTest extends TestCase
{
    public function test_it_stores_one_hundred_pixel_webp_thumbnail_for_images(): void
    {
        Storage::fake('local');

        $path = app(FeedAttachmentThumbnail::class)->store(
            UploadedFile::fake()->image('cover.jpg', 640, 360),
        );

        $this->assertNotNull($path);
        $this->assertStringStartsWith('feed-attachment-thumbnails/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('local')->assertExists($path);

        $thumbnail = (new ImageManager(new GdDriver))
            ->decode(Storage::disk('local')->path($path));

        $this->assertSame(100, $thumbnail->width());
        $this->assertSame(100, $thumbnail->height());
    }

    public function test_it_skips_non_image_attachments(): void
    {
        Storage::fake('local');

        $path = app(FeedAttachmentThumbnail::class)->store(
            UploadedFile::fake()->create('guide.pdf', 64, 'application/pdf'),
        );

        $this->assertNull($path);
        Storage::disk('local')->assertDirectoryEmpty('feed-attachment-thumbnails');
    }
}
