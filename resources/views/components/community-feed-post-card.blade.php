@props([
    'card',
])

@php
    $post = $card->communityPost;
    $createdAt = $post?->created_at ?? $card->sortAt;
@endphp

<article class="feed-post community-feed-post">
    <header class="feed-post-header">
        <div class="feed-avatar community-feed-avatar">
            {{ mb_strtoupper(mb_substr($card->communityName ?? 'C', 0, 1)) }}
        </div>
        <div class="feed-author">
            <div class="feed-author-line">
                <strong>{{ $card->authorDisplayName ?? 'member' }}</strong>
                <span class="feed-pseudo-badge">Community post</span>
            </div>
            <time datetime="{{ $createdAt->toIso8601String() }}">{{ $createdAt->diffForHumans() }}</time>
        </div>
        <span class="feed-privacy-badge {{ $card->communityVisibility === \App\Models\Community::VISIBILITY_PUBLIC ? 'public' : '' }}">
            {{ $card->communityVisibility ?? 'community' }}
        </span>
    </header>

    <div class="community-feed-meta">
        <strong>{{ $card->communityName }}</strong>
        @if($card->topicName)
            <span>{{ $card->topicName }}</span>
        @endif
    </div>

    @if($post?->isPlaintext())
        <div class="feed-post-body">{{ $post->body }}</div>
    @else
        <div class="community-feed-placeholder">
            <strong>Encrypted community post</strong>
            <span>Encrypted post</span>
        </div>
    @endif
</article>
