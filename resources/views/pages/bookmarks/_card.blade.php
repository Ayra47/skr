@if($bookmark->bookmarkable_type === \App\Models\CommunityPost::class)
    @include('pages.bookmarks._community_card', ['bookmark' => $bookmark])
@else
@php
    $isDeleted = $bookmark->original_deleted;
    $isWhisper = $bookmark->snapshot_is_whisper;
    $authorName = $isWhisper ? 'анонимный автор' : ($bookmark->snapshot_author_name ?? 'неизвестен');
    $initial = $isWhisper ? null : mb_strtoupper(mb_substr($authorName, 0, 1));
@endphp

<article class="bm-card {{ $isWhisper ? 'bm-card--whisper' : '' }} {{ $isDeleted ? 'bm-card--deleted' : '' }}">

    {{-- Top meta row: source · saved-at · remove --}}
    <div class="bm-card-meta-row">
        @if($bookmark->source_label)
            <span class="bm-card-source-badge">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12L12 4l9 8"/><path d="M5 10v10h14V10"/>
                </svg>
                {{ $bookmark->source_label }}
            </span>
        @endif
        @if($isDeleted)
            <span class="bm-card-deleted-badge">удалено</span>
        @endif
        <span class="bm-card-saved-at">сохранено {{ $bookmark->created_at->diffForHumans() }}</span>
        <button
            class="bm-card-remove-btn"
            type="button"
            wire:click="remove({{ $bookmark->id }})"
            title="Убрать из закладок"
            aria-label="Убрать из закладок"
        >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                <path d="M6 3a2 2 0 0 0-2 2v16l8-5 8 5V5a2 2 0 0 0-2-2H6z"/>
            </svg>
        </button>
    </div>

    {{-- Author header --}}
    <header class="bm-card-header">
        <div class="bm-card-avatar {{ $isWhisper ? 'bm-card-avatar--whisper' : '' }}">
            @if($isWhisper)
                ?
            @else
                {{ $initial }}
            @endif
        </div>
        <div class="bm-card-author-info">
            @if($isWhisper)
                <div class="bm-card-author-row">
                    <span class="bm-card-author bm-card-author--whisper">{{ $authorName }}</span>
                    <span class="bm-card-whisper-badge">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 11a3 3 0 0 1 6 0"/><path d="M12 19c-3 0-7-2-7-7s4-7 7-7 7 2 7 7-4 7-7 7z"/>
                        </svg>
                        whisper
                    </span>
                </div>
            @else
                <span class="bm-card-author">{{ $authorName }}</span>
            @endif
            <div class="bm-card-time">{{ $bookmark->snapshot_posted_at->diffForHumans() }}</div>
        </div>
    </header>

    {{-- Body --}}
    @if($bookmark->snapshot_body)
        <p class="bm-card-body">{{ $bookmark->snapshot_body }}</p>
    @endif

    {{-- Attachments gallery --}}
    @include('pages.bookmarks._gallery', ['bookmark' => $bookmark])

    {{-- Footer --}}
    <footer class="bm-card-footer">
        <span class="bm-card-footer-note">хранится только у вас, зашифровано</span>
        @if(! $isDeleted)
            <a
                href="{{ route('feed.index') }}?post={{ $bookmark->bookmarkable_id }}"
                class="bm-card-open-btn"
                target="_blank"
                rel="noopener"
            >
                Открыть
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>
                </svg>
            </a>
        @endif
    </footer>

</article>
@endif
