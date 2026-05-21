<?php

namespace Tests\Unit;

use Tests\TestCase;

class FeedPreviewAssetsTest extends TestCase
{
    public function test_attachment_previews_use_compact_media_tiles(): void
    {
        $stylesheet = file_get_contents(resource_path('css/pages/feed.scss'));
        $script = file_get_contents(resource_path('js/pages/feed.ts'));

        $this->assertStringContainsString('grid-auto-columns: 80px;', $stylesheet);
        $this->assertStringContainsString('width: 80px;', $stylesheet);
        $this->assertStringContainsString('? "media" : "file"', $script);
        $this->assertStringNotContainsString('formatBytes(preview.file.size)', $script);
    }

    public function test_post_gallery_uses_large_active_item_with_square_thumbnails(): void
    {
        $galleryStylesheet = file_get_contents(resource_path('css/components/gallery.scss'));
        $galleryTemplate = file_get_contents(resource_path('views/livewire/partials/feed-attachments-gallery.blade.php'));

        $this->assertStringContainsString('data-feed-gallery', $galleryTemplate);
        $this->assertStringContainsString('data-feed-gallery-main-item', $galleryTemplate);
        $this->assertStringContainsString('data-feed-gallery-thumb', $galleryTemplate);
        $this->assertStringContainsString('grid-auto-columns: 100px;', $galleryStylesheet);
        $this->assertStringContainsString('width: 100px;', $galleryStylesheet);
        $this->assertStringContainsString('height: 100px;', $galleryStylesheet);
    }

    public function test_post_gallery_keeps_stable_sixteen_by_nine_stage_for_all_attachment_types(): void
    {
        $galleryStylesheet = file_get_contents(resource_path('css/components/gallery.scss'));

        $this->assertMatchesRegularExpression('/\\.feed-gallery-main-item\\s*\\{[^}]*aspect-ratio:\\s*16\\s*\\/\\s*9;/s', $galleryStylesheet);
        $this->assertMatchesRegularExpression('/\\.feed-gallery-media img,[^}]*object-fit:\\s*contain;/s', $galleryStylesheet);
        $this->assertMatchesRegularExpression('/\\.feed-gallery-file-card\\s*\\{[^}]*height:\\s*100%;/s', $galleryStylesheet);
    }

    public function test_gallery_file_card_uses_centered_rich_layout(): void
    {
        $galleryStylesheet = file_get_contents(resource_path('css/components/gallery.scss'));
        $galleryTemplate = file_get_contents(resource_path('views/livewire/partials/feed-attachments-gallery.blade.php'));

        $this->assertStringContainsString('feed-gallery-file-meta', $galleryTemplate);
        $this->assertStringContainsString('feed-gallery-file-action', $galleryTemplate);
        $this->assertMatchesRegularExpression('/\\.feed-gallery-file-card\\s*\\{[^}]*flex-direction:\\s*column;/s', $galleryStylesheet);
        $this->assertMatchesRegularExpression('/\\.feed-gallery-file-card \\.feed-file-icon\\s*\\{[^}]*width:\\s*84px;/s', $galleryStylesheet);
    }

    public function test_post_gallery_requests_small_images_for_thumbnail_strip(): void
    {
        $galleryTemplate = file_get_contents(resource_path('views/livewire/partials/feed-attachments-gallery.blade.php'));

        $this->assertStringContainsString("'thumbnail' => 1", $galleryTemplate);
    }

    public function test_modal_gallery_exposes_fullscreen_viewer_controls_for_media(): void
    {
        $script = file_get_contents(resource_path('js/pages/feed.ts'));

        $this->assertStringContainsString('openFeedGalleryViewer', $script);
        $this->assertStringContainsString('data-feed-gallery-open-viewer', $script);
        $this->assertStringContainsString('data-feed-gallery-viewer-next', $script);
        $this->assertStringContainsString('data-feed-gallery-viewer-prev', $script);
    }

    public function test_gallery_thumbnails_use_single_line_swiper_controls(): void
    {
        $stylesheet = file_get_contents(resource_path('css/pages/feed.scss'));
        $galleryTemplate = file_get_contents(resource_path('views/livewire/partials/feed-attachments-gallery.blade.php'));
        $script = file_get_contents(resource_path('js/pages/feed.ts'));

        $this->assertStringContainsString('data-feed-gallery-thumbs-track', $galleryTemplate);
        $this->assertStringContainsString('data-feed-gallery-thumbs-prev', $galleryTemplate);
        $this->assertStringContainsString('data-feed-gallery-thumbs-next', $galleryTemplate);
        $this->assertStringContainsString('grid-auto-flow: column;', $stylesheet);
        $this->assertStringContainsString('overflow-x: auto;', $stylesheet);
        $this->assertStringContainsString('updateFeedGalleryThumbControls', $script);
    }

    public function test_composer_attachment_previews_use_eighty_pixel_swiper_tiles(): void
    {
        $stylesheet = file_get_contents(resource_path('css/pages/feed.scss'));
        $script = file_get_contents(resource_path('js/pages/feed.ts'));

        $this->assertStringContainsString('data-feed-attachment-previews-track', $script);
        $this->assertStringContainsString('data-feed-attachment-previews-prev', $script);
        $this->assertStringContainsString('data-feed-attachment-previews-next', $script);
        $this->assertStringContainsString('grid-auto-columns: 80px;', $stylesheet);
        $this->assertStringContainsString('width: 80px;', $stylesheet);
        $this->assertStringContainsString('height: 80px;', $stylesheet);
    }

    public function test_post_cards_can_shrink_inside_feed_width_when_thumbnail_strip_overflows(): void
    {
        $stylesheet = file_get_contents(resource_path('css/pages/feed.scss'));
        $template = file_get_contents(resource_path('views/livewire/feed.blade.php'));

        $this->assertStringContainsString('class="feed-post-shell"', $template);
        $this->assertMatchesRegularExpression('/\\.feed-post-shell\\s*\\{[^}]*min-width:\\s*0;/s', $stylesheet);
        $this->assertMatchesRegularExpression('/\\.feed-post\\s*\\{[^}]*min-width:\\s*0;/s', $stylesheet);
    }

    public function test_composer_exposes_visible_upload_progress_state(): void
    {
        $stylesheet = file_get_contents(resource_path('css/pages/feed.scss'));
        $script = file_get_contents(resource_path('js/pages/feed.ts'));
        $template = file_get_contents(resource_path('views/livewire/feed.blade.php'));

        $this->assertStringContainsString('data-feed-upload-progress', $template);
        $this->assertStringContainsString('data-feed-upload-progress-bar', $template);
        $this->assertStringContainsString('livewire-upload-progress', $script);
        $this->assertStringContainsString('.feed-upload-progress-track', $stylesheet);
    }

    public function test_whisper_toggle_is_handled_locally_without_a_livewire_action(): void
    {
        $script = file_get_contents(resource_path('js/pages/feed.ts'));
        $template = file_get_contents(resource_path('views/livewire/feed.blade.php'));

        $this->assertStringContainsString('data-feed-whisper-input', $template);
        $this->assertStringContainsString('data-feed-whisper-toggle', $template);
        $this->assertStringNotContainsString('wire:click="toggleWhisper"', $template);
        $this->assertStringContainsString('toggleWhisperComposerState', $script);
        $this->assertStringContainsString('syncWhisperComposerState', $script);
    }

    public function test_whisper_mode_exposes_anonymity_hint(): void
    {
        $stylesheet = file_get_contents(resource_path('css/pages/feed.scss'));
        $template = file_get_contents(resource_path('views/livewire/feed.blade.php'));

        $this->assertStringContainsString('data-feed-whisper-hint', $template);
        $this->assertStringContainsString('Абсолютно анонимный пост, даже ваш псевдоним будет скрыт.', $template);
        $this->assertStringContainsString('.feed-whisper-hint', $stylesheet);
        $this->assertStringContainsString('color: rgb(91, 96, 109);', $stylesheet);
    }
}
