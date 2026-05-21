@php
    $isUnavailable = (bool) $bookmark->getAttribute('community_bookmark_unavailable');
    $card = $bookmark->getAttribute('community_post_card');
    $createdAt = $card?->communityPost?->created_at ?? $bookmark->snapshot_posted_at;
@endphp

<article class="bm-card {{ $isUnavailable ? 'bm-card--deleted' : '' }}">
    <div class="bm-card-meta-row">
        @if($bookmark->source_label)
            <span class="bm-card-source-badge">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
                {{ $bookmark->source_label }}
            </span>
        @endif
        @if($isUnavailable)
            <span class="bm-card-deleted-badge">недоступно</span>
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

    @if($isUnavailable || ! $card)
        <div class="bm-card-header">
            <div class="bm-card-avatar">?</div>
            <div class="bm-card-author-info">
                <span class="bm-card-author">Community post unavailable</span>
                <div class="bm-card-time">{{ $createdAt?->diffForHumans() ?? 'source unavailable' }}</div>
            </div>
        </div>
        <p class="bm-card-body">This saved community post is locked, deleted, or no longer available.</p>
    @else
        <header class="bm-card-header">
            <div class="bm-card-avatar">
                {{ mb_strtoupper(mb_substr($card->communityName ?? 'C', 0, 1)) }}
            </div>
            <div class="bm-card-author-info">
                <span class="bm-card-author">{{ $card->authorDisplayName ?? 'member' }}</span>
                <div class="bm-card-time">{{ $createdAt->diffForHumans() }}</div>
            </div>
        </header>

        <div class="community-feed-meta">
            <strong>{{ $card->communityName }}</strong>
            @if($card->topicName)
                <span>{{ $card->topicName }}</span>
            @endif
        </div>

        @if($card->communityPost?->isPlaintext())
            <div class="feed-post-body">{{ $card->communityPost->body }}</div>
        @else
            <div class="community-feed-placeholder">
                <strong>Encrypted community post</strong>
                <span>Encrypted post</span>
            </div>
        @endif
    @endif

    <footer class="bm-card-footer">
        <span class="bm-card-footer-note">source reference only, community body not copied</span>
    </footer>
</article>
