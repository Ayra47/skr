@php
    $tabs = [
        'friends' => 'Друзья',
        'all' => 'Все',
        'mine' => 'Мои',
    ];
    $lifetimeOptions = [
        \App\Models\FeedPost::EXPIRES_1H => '1 час',
        \App\Models\FeedPost::EXPIRES_24H => '24 часа',
        \App\Models\FeedPost::EXPIRES_7D => '7 дней',
        \App\Models\FeedPost::EXPIRES_FOREVER => 'Навсегда',
    ];

    $answerLabel = static function (int $count): string {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 'ответ';
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 'ответа';
        }

        return 'ответов';
    };

    $formatBytes = static function (?int $bytes): string {
        if (! $bytes) {
            return '';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' КБ';
        }

        return round($bytes / 1024 / 1024, 1).' МБ';
    };

    $userFeedName = $user->feedName();
@endphp

<main class="feed-root">
    <div class="feed-glow"></div>
    <div class="feed-inner">
        <header class="feed-header">
            <h1>Лента</h1>
            <p>Посты под псевдонимами. Можно писать для друзей или для всех.</p>
        </header>

        <nav class="feed-tabs" aria-label="Фильтр ленты">
            @foreach($tabs as $key => $label)
                <button class="feed-tab {{ $tab === $key ? 'active' : '' }}" type="button" wire:click="setTab('{{ $key }}')">
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        <section class="feed-composer" aria-label="Создать пост">
            <form class="feed-composer-form" wire:submit="createPost" enctype="multipart/form-data">
                <div class="feed-composer-row">
                    <div class="feed-avatar">
                        @if($user->avatar)
                            <img src="/storage/{{ $user->avatar }}" alt="">
                        @else
                            {{ mb_strtoupper(mb_substr($userFeedName, 0, 1)) }}
                        @endif
                    </div>
                    <div class="feed-composer-main">
                        <textarea class="feed-textarea" wire:model="body" rows="2" maxlength="2000" placeholder="Поделитесь чем-то..." data-feed-textarea></textarea>

                        <div class="feed-attachment-previews" data-feed-attachment-preview data-feed-attachment-previews wire:ignore hidden></div>

                        <div class="feed-upload-progress" data-feed-upload-progress hidden>
                            <div class="feed-upload-progress-head">
                                <span data-feed-upload-progress-label>Загрузка файлов</span>
                                <strong data-feed-upload-progress-value>0%</strong>
                            </div>
                            <div class="feed-upload-progress-track" aria-hidden="true">
                                <span data-feed-upload-progress-bar></span>
                            </div>
                        </div>
                        <div wire:loading wire:target="createPost" class="feed-uploading">Публикуем пост...</div>

                        @if($errors->any())
                            <div class="feed-errors">
                                @foreach($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div class="feed-composer-actions">
                            <label class="feed-file-button">
                                <input type="file" wire:model="attachments" data-feed-attachment multiple accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.pdf,.txt,.md,.zip">
                                <span>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <path d="M14 2v6h6"/>
                                    </svg>
                                    Файл
                                </span>
                            </label>

                            <div class="feed-visibility-toggle" role="radiogroup" aria-label="Видимость поста">
                                <label>
                                    <input type="radio" wire:model="visibility" value="{{ \App\Models\FeedPost::VISIBILITY_FRIENDS }}" data-feed-visibility-friends @disabled($isWhisper)>
                                    <span>для друзей</span>
                                </label>
                                <label>
                                    <input type="radio" wire:model="visibility" value="{{ \App\Models\FeedPost::VISIBILITY_PUBLIC }}" data-feed-visibility-public>
                                    <span>для всех</span>
                                </label>
                            </div>

                            <div class="feed-lifetime-toggle" role="radiogroup" aria-label="Время жизни поста">
                                @foreach($lifetimeOptions as $value => $label)
                                    <label>
                                        <input type="radio" wire:model="expiresIn" value="{{ $value }}">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <input type="checkbox" wire:model="isWhisper" data-feed-whisper-input hidden>

                            <button class="feed-whisper-toggle {{ $isWhisper ? 'active' : '' }}" type="button" data-feed-whisper-toggle aria-pressed="{{ $isWhisper ? 'true' : 'false' }}" title="Тихий пост — без псевдонима">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 18V5l12-2v13"/>
                                    <circle cx="6" cy="18" r="3"/>
                                    <circle cx="18" cy="16" r="3"/>
                                </svg>
                                Тихо
                            </button>

                            <button class="feed-submit" type="submit" data-feed-submit wire:loading.attr="disabled" wire:target="createPost,attachments" disabled>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 2 11 13"/>
                                    <path d="m22 2-7 20-4-9-9-4 20-7z"/>
                                </svg>
                                Опубликовать
                            </button>
                        </div>

                        <div class="feed-whisper-hint" data-feed-whisper-hint @if(! $isWhisper) hidden @endif>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="10" width="16" height="11" rx="2"/>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                            </svg>
                            <span>Абсолютно анонимный пост, даже ваш псевдоним будет скрыт.</span>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <section class="feed-post-list" aria-label="Посты">
            @forelse($posts as $post)
                @php $myVote = $post->votes->first()?->value; @endphp

                <div class="feed-post-shell" wire:key="feed-post-{{ $post->id }}">
                    <x-feed-post-card
                        :post="$post"
                        :format-bytes="$formatBytes"
                        :my-vote="$myVote"
                        :answer-label="$answerLabel"
                        :interactive="true"
                        :expanded-comment-posts="$expandedCommentPosts"
                    />

                    @if(! ($expandedCommentPosts[$post->id] ?? false) && $topComments->has($post->id))
                        <div class="feed-top-reply" wire:click.stop>
                            @include('livewire.partials.feed-comment', [
                                'comment' => $topComments->get($post->id),
                                'context' => 'top',
                                'depth' => 0,
                                'modalCommentsByParent' => collect(),
                                'modalReplyCounts' => collect(),
                                'visibleReplyLimits' => $visibleReplyLimits,
                            ])
                        </div>
                    @endif

                    <div class="feed-replies-panel {{ ($expandedCommentPosts[$post->id] ?? false) ? 'open' : '' }}" data-feed-replies-panel data-post-id="{{ $post->id }}" wire:click.stop>
                        @if($expandedCommentPosts[$post->id] ?? false)
                            @forelse($previewComments->get($post->id, collect()) as $comment)
                                @include('livewire.partials.feed-comment', [
                                    'comment' => $comment,
                                    'context' => 'preview',
                                    'depth' => 0,
                                    'modalCommentsByParent' => $previewCommentsByParent,
                                    'modalReplyCounts' => $previewReplyCounts,
                                    'visibleReplyLimits' => $visibleReplyLimits,
                                ])
                            @empty
                                <div class="feed-reply-empty">Ответов пока нет</div>
                            @endforelse

                            @if($post->root_comments_count > ($previewCommentLimits[$post->id] ?? 3))
                                <button class="feed-hidden-load-more" type="button" data-feed-replies-load-more wire:click="loadMorePostComments({{ $post->id }})" aria-hidden="true" tabindex="-1"></button>
                            @endif
                        @endif
                    </div>

                    <form class="feed-comment-form" wire:submit="createComment({{ $post->id }})" wire:click.stop>
                        <input type="text" wire:model="commentBodies.{{ $post->id }}" maxlength="1000" placeholder="Ответить под псевдонимом">
                        <button type="submit" wire:loading.attr="disabled" wire:target="createComment({{ $post->id }})">Ответить</button>
                    </form>
                </div>
            @empty
                <div class="feed-empty">
                    <strong>Пока пусто</strong>
                    <span>Первый пост появится здесь.</span>
                </div>
            @endforelse
        </section>

        @if($posts->hasPages())
            <div class="feed-pagination">
                {{ $posts->links() }}
            </div>
        @endif
    </div>

    @if($modalPost)
        @php
            $modalAuthorFeedName = $modalPost->author?->feedName();
        @endphp

        <div class="feed-modal-backdrop" wire:key="feed-post-modal">
            <section class="feed-modal" role="dialog" aria-modal="true" aria-label="Пост и ответы">
                <header class="feed-modal-header">
                    <div class="feed-post-header">
                        <div class="feed-avatar {{ $modalPost->is_whisper ? 'whisper' : '' }}">
                            @if($modalPost->is_whisper)
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                            @elseif($modalPost->author?->avatar)
                                <img src="/storage/{{ $modalPost->author->avatar }}" alt="">
                            @else
                                {{ mb_strtoupper(mb_substr($modalAuthorFeedName, 0, 1)) }}
                            @endif
                        </div>
                        <div class="feed-author">
                            <div class="feed-author-line">
                                @if($modalPost->is_whisper)
                                    <strong class="feed-anon-author">анонимный автор</strong>
                                    <span class="feed-whisper-badge">WHISPER</span>
                                @else
                                    <strong>{{ $modalAuthorFeedName }}</strong>
                                    <span class="feed-pseudo-badge">псевдоним</span>
                                @endif
                            </div>
                            <time datetime="{{ $modalPost->created_at->toIso8601String() }}">{{ $modalPost->created_at->diffForHumans() }}</time>
                        </div>
                    </div>
                    <button class="feed-modal-close" type="button" wire:click="closePost" aria-label="Закрыть">×</button>
                </header>

                @if($modalPost->body)
                    <div class="feed-post-body">{{ $modalPost->body }}</div>
                @endif

                @include('livewire.partials.feed-attachments-gallery', [
                    'attachments' => $modalPost->attachments,
                    'formatBytes' => $formatBytes,
                    'isModal' => true,
                    'post' => $modalPost,
                ])

                <form class="feed-comment-form feed-modal-comment-form" wire:submit="createComment({{ $modalPost->id }})">
                    <input type="text" wire:model="commentBodies.{{ $modalPost->id }}" maxlength="1000" placeholder="Ответить под псевдонимом">
                    <button type="submit" wire:loading.attr="disabled" wire:target="createComment({{ $modalPost->id }})">Ответить</button>
                </form>

                <div class="feed-modal-replies">
                    <h2>Все ответы</h2>
                    @forelse($modalCommentsByParent->get(0, collect()) as $comment)
                        @include('livewire.partials.feed-comment', [
                            'comment' => $comment,
                            'context' => 'modal',
                            'depth' => 0,
                            'modalCommentsByParent' => $modalCommentsByParent,
                            'modalReplyCounts' => $modalReplyCounts,
                            'visibleReplyLimits' => $visibleReplyLimits,
                        ])
                    @empty
                        <div class="feed-reply-empty">Ответов пока нет</div>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
</main>
