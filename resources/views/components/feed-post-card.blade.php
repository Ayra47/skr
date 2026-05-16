@props([
    'post',
    'formatBytes',
    'interactive' => false,
    'myVote' => null,
    'answerLabel' => null,
    'expandedCommentPosts' => [],
])

@php
    $authorFeedName = $post->author?->feedName();
    $answerText = $answerLabel ? $answerLabel($post->comments_count) : 'ответов';
@endphp

<article class="feed-post">
    <div class="feed-post-open-area"
        @if($interactive)
            wire:click="openPost({{ $post->id }})"
            wire:keydown.enter="openPost({{ $post->id }})"
            role="button"
            tabindex="0"
        @endif
    >
        <header class="feed-post-header">
            <div class="feed-avatar {{ $post->is_whisper ? 'whisper' : '' }}">
                @if($post->is_whisper)
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                @elseif($post->author?->avatar)
                    <img src="/storage/{{ $post->author->avatar }}" alt="">
                @else
                    {{ mb_strtoupper(mb_substr($authorFeedName, 0, 1)) }}
                @endif
            </div>
            <div class="feed-author">
                <div class="feed-author-line">
                    @if($post->is_whisper)
                        <strong class="feed-anon-author">анонимный автор</strong>
                        <span class="feed-whisper-badge">WHISPER</span>
                    @else
                        <strong>{{ $authorFeedName }}</strong>
                        <span class="feed-pseudo-badge">псевдоним</span>
                    @endif
                </div>
                <time datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</time>
                @if($post->expires_at)
                    <span class="feed-expires">до {{ $post->expires_at->diffForHumans() }}</span>
                @endif
            </div>
            <span class="feed-privacy-badge {{ $post->visibility === \App\Models\FeedPost::VISIBILITY_PUBLIC ? 'public' : '' }}">
                {{ $post->visibility === \App\Models\FeedPost::VISIBILITY_PUBLIC ? 'для всех' : 'для друзей' }}
            </span>
        </header>

        @if($post->body)
            <div class="feed-post-body">{{ $post->body }}</div>
        @endif
    </div>

    @include('livewire.partials.feed-attachments-gallery', [
        'attachments' => $post->attachments,
        'formatBytes' => $formatBytes,
        'post' => $post,
    ])

    <footer class="feed-post-footer">
        <div class="feed-actions">
            @if($interactive)
                <button class="feed-reaction-btn {{ $myVote === \App\Models\FeedVote::VALUE_UP ? 'active-up' : '' }}" type="button" wire:click="vote({{ $post->id }}, '{{ \App\Models\FeedVote::VALUE_UP }}')" aria-label="Голос за">
            @else
                <span class="feed-reaction-btn {{ $myVote === \App\Models\FeedVote::VALUE_UP ? 'active-up' : '' }}">
            @endif
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m7 14 5-5 5 5"/>
                </svg>
                {{ $post->up_votes_count }}
            @if($interactive)
                </button>
            @else
                </span>
            @endif

            @if($interactive)
                <button class="feed-reaction-btn {{ $myVote === \App\Models\FeedVote::VALUE_DOWN ? 'active-down' : '' }}" type="button" wire:click="vote({{ $post->id }}, '{{ \App\Models\FeedVote::VALUE_DOWN }}')" aria-label="Голос против">
            @else
                <span class="feed-reaction-btn {{ $myVote === \App\Models\FeedVote::VALUE_DOWN ? 'active-down' : '' }}">
            @endif
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m7 10 5 5 5-5"/>
                </svg>
                {{ $post->down_votes_count }}
            @if($interactive)
                </button>
            @else
                </span>
            @endif

            @if($interactive)
                <button class="feed-comments-count {{ ($expandedCommentPosts[$post->id] ?? false) ? 'active' : '' }}" type="button" wire:click="toggleComments({{ $post->id }})" aria-expanded="{{ ($expandedCommentPosts[$post->id] ?? false) ? 'true' : 'false' }}">
            @else
                <span class="feed-comments-count">
            @endif
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 11.5a8.4 8.4 0 0 1-7.6 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 8-8h.5a8.5 8.5 0 0 1 8 8z"/>
                </svg>
                {{ $post->comments_count }} {{ $answerText }}
            @if($interactive)
                </button>
            @else
                </span>
            @endif
        </div>
        <span class="feed-anonymous-note">комментарии с псевдонимами</span>
    </footer>
</article>
