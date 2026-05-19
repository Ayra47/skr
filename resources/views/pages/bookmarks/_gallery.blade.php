@if($bookmark->attachments->isNotEmpty())
    @php
        $mediaAttachments = $bookmark->attachments->filter(fn ($a) => $a->isImage() || $a->isVideo());
        $allAttachments = $bookmark->attachments;
        $formatBytes = fn (?int $bytes): string => match(true) {
            $bytes === null => '',
            $bytes >= 1048576 => round($bytes / 1048576, 1).' МБ',
            default => round($bytes / 1024).' КБ',
        };
    @endphp

    <div class="feed-gallery" data-feed-gallery data-gallery-modal="1">
        <div class="feed-gallery-stage">
            @foreach($allAttachments as $attachment)
                @php
                    $index = $loop->index;
                    $isImage = $attachment->isImage();
                    $isVideo = $attachment->isVideo();
                    $isMedia = $isImage || $isVideo;
                    $attachmentUrl = route('bookmarks.attachments.show', [$bookmark, $attachment]);
                @endphp

                <div
                    class="feed-gallery-main-item {{ $loop->first ? 'active' : '' }}"
                    data-feed-gallery-main-item
                    data-gallery-index="{{ $index }}"
                    data-gallery-media="{{ $isMedia ? '1' : '0' }}"
                    data-gallery-kind="{{ $isImage ? 'image' : ($isVideo ? 'video' : 'file') }}"
                    data-gallery-url="{{ $attachmentUrl }}"
                    data-gallery-mime="{{ $attachment->mime }}"
                    data-gallery-name="{{ $attachment->downloadName() }}"
                    @if(! $loop->first) hidden @endif
                >
                    @if($isImage)
                        <button class="feed-gallery-media-button" type="button" data-feed-gallery-open-viewer aria-label="Открыть изображение">
                            <img src="{{ $attachmentUrl }}" alt="{{ $attachment->downloadName() }}">
                        </button>
                    @elseif($isVideo)
                        <button class="feed-gallery-media-button" type="button" data-feed-gallery-open-viewer aria-label="Открыть видео">
                            <video muted preload="metadata">
                                <source src="{{ $attachmentUrl }}" type="{{ $attachment->mime }}">
                            </video>
                            <span class="feed-gallery-play-badge" aria-hidden="true"></span>
                        </button>
                    @else
                        <a class="feed-post-file feed-gallery-file-card" href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                            <span class="feed-file-icon">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <path d="M14 2v6h6"/>
                                </svg>
                            </span>
                            <span class="feed-gallery-file-copy">
                                <strong>{{ $attachment->downloadName() }}</strong>
                                @if($attachment->size)
                                    <small class="feed-gallery-file-meta">
                                        {{ mb_strtoupper(pathinfo($attachment->name ?? '', PATHINFO_EXTENSION) ?: 'Файл') }}
                                        · {{ $formatBytes($attachment->size) }}
                                    </small>
                                @endif
                            </span>
                            <em class="feed-gallery-file-action">Открыть файл</em>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        @if($allAttachments->count() > 1)
            <div class="feed-gallery-thumbs-shell">
                <button class="feed-gallery-thumbs-arrow prev" type="button" data-feed-gallery-thumbs-prev aria-label="Прокрутить назад" hidden>‹</button>
                <div class="feed-gallery-thumbs" data-feed-gallery-thumbs-track aria-label="Вложения">
                    @foreach($allAttachments as $attachment)
                        @php
                            $index = $loop->index;
                            $isImage = $attachment->isImage();
                            $isVideo = $attachment->isVideo();
                            $attachmentUrl = route('bookmarks.attachments.show', [$bookmark, $attachment]);
                            $thumbnailUrl = filled($attachment->thumbnail_path)
                                ? route('bookmarks.attachments.show', [$bookmark, $attachment, 'thumbnail' => 1])
                                : $attachmentUrl;
                        @endphp

                        <button
                            class="feed-gallery-thumb {{ $loop->first ? 'active' : '' }}"
                            type="button"
                            data-feed-gallery-thumb
                            data-gallery-index="{{ $index }}"
                            aria-label="Показать вложение {{ $index + 1 }}"
                        >
                            @if($isImage)
                                <img src="{{ $thumbnailUrl }}" alt="">
                            @elseif($isVideo)
                                <video muted preload="metadata">
                                    <source src="{{ $attachmentUrl }}" type="{{ $attachment->mime }}">
                                </video>
                                <span class="feed-gallery-play-badge" aria-hidden="true"></span>
                            @else
                                <span class="feed-gallery-file-thumb" aria-hidden="true"></span>
                            @endif
                        </button>
                    @endforeach
                </div>
                <button class="feed-gallery-thumbs-arrow next" type="button" data-feed-gallery-thumbs-next aria-label="Прокрутить вперёд" hidden>›</button>
            </div>
        @endif
    </div>
@endif
