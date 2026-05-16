@php
    $isModal = $isModal ?? false;
@endphp

@if($attachments->isNotEmpty())
    <div class="feed-gallery" data-feed-gallery data-gallery-modal="{{ $isModal ? '1' : '0' }}">
        <div class="feed-gallery-stage">
            @foreach($attachments as $attachment)
                @php
                    $index = $loop->index;
                    $isImageAttachment = is_string($attachment->mime) && str_starts_with($attachment->mime, 'image/');
                    $isVideoAttachment = is_string($attachment->mime) && str_starts_with($attachment->mime, 'video/');
                    $isMediaAttachment = $isImageAttachment || $isVideoAttachment;
                    $attachmentUrl = route('feed.posts.attachments.show', [$post, $attachment]);
                @endphp

                <div
                    class="feed-gallery-main-item {{ $loop->first ? 'active' : '' }}"
                    data-feed-gallery-main-item
                    data-gallery-index="{{ $index }}"
                    data-gallery-media="{{ $isMediaAttachment ? '1' : '0' }}"
                    data-gallery-kind="{{ $isImageAttachment ? 'image' : ($isVideoAttachment ? 'video' : 'file') }}"
                    data-gallery-url="{{ $attachmentUrl }}"
                    data-gallery-mime="{{ $attachment->mime }}"
                    data-gallery-name="{{ $attachment->name }}"
                    @if(! $loop->first) hidden @endif
                >
                    @if($isImageAttachment)
                        @if($isModal)
                            <button class="feed-gallery-media-button" type="button" data-feed-gallery-open-viewer aria-label="Открыть изображение">
                                <img src="{{ $attachmentUrl }}" alt="{{ $attachment->name }}">
                            </button>
                        @else
                            <div class="feed-gallery-media">
                                <img src="{{ $attachmentUrl }}" alt="{{ $attachment->name }}">
                            </div>
                        @endif
                    @elseif($isVideoAttachment)
                        @if($isModal)
                            <button class="feed-gallery-media-button" type="button" data-feed-gallery-open-viewer aria-label="Открыть видео">
                                <video muted preload="metadata">
                                    <source src="{{ $attachmentUrl }}" type="{{ $attachment->mime }}">
                                </video>
                                <span class="feed-gallery-play-badge" aria-hidden="true"></span>
                            </button>
                        @else
                            <div class="feed-gallery-media">
                                <video controls preload="metadata">
                                    <source src="{{ $attachmentUrl }}" type="{{ $attachment->mime }}">
                                </video>
                            </div>
                        @endif
                    @else
                        <a class="feed-post-file feed-gallery-file-card" href="{{ $attachmentUrl }}">
                            <span class="feed-file-icon">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <path d="M14 2v6h6"/>
                                </svg>
                            </span>
                            <span class="feed-gallery-file-copy">
                                <strong>{{ $attachment->name }}</strong>
                                <small class="feed-gallery-file-meta">
                                    {{ mb_strtoupper(pathinfo($attachment->name, PATHINFO_EXTENSION) ?: 'Файл') }}
                                    · {{ $formatBytes($attachment->size) }}
                                    · зашифрован
                                </small>
                            </span>
                            <em class="feed-gallery-file-action">Открыть файл</em>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        @if($attachments->count() > 1)
            <div class="feed-gallery-thumbs-shell">
                <button class="feed-gallery-thumbs-arrow prev" type="button" data-feed-gallery-thumbs-prev aria-label="Прокрутить вложения назад" hidden>‹</button>
                <div class="feed-gallery-thumbs" data-feed-gallery-thumbs-track aria-label="Вложения">
                    @foreach($attachments as $attachment)
                        @php
                            $index = $loop->index;
                            $isImageAttachment = is_string($attachment->mime) && str_starts_with($attachment->mime, 'image/');
                            $isVideoAttachment = is_string($attachment->mime) && str_starts_with($attachment->mime, 'video/');
                            $attachmentUrl = route('feed.posts.attachments.show', [$post, $attachment]);
                            $thumbnailUrl = $isImageAttachment && filled($attachment->thumbnail_path)
                                ? route('feed.posts.attachments.show', [
                                    'post' => $post,
                                    'attachment' => $attachment,
                                    'thumbnail' => 1,
                                ])
                                : $attachmentUrl;
                        @endphp

                        <button
                            class="feed-gallery-thumb {{ $loop->first ? 'active' : '' }}"
                            type="button"
                            data-feed-gallery-thumb
                            data-gallery-index="{{ $index }}"
                            aria-label="Показать вложение {{ $index + 1 }}"
                        >
                            @if($isImageAttachment)
                                <img src="{{ $thumbnailUrl }}" alt="">
                            @elseif($isVideoAttachment)
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
                <button class="feed-gallery-thumbs-arrow next" type="button" data-feed-gallery-thumbs-next aria-label="Прокрутить вложения вперед" hidden>›</button>
            </div>
        @endif
    </div>
@endif
