@php
    $commentAuthorName = $comment->user->feedName();
    $commentVote = $comment->votes->first()?->value;
    $replyCount = (int) ($modalReplyCounts->get($comment->id, 0));
    $visibleReplyLimit = (int) ($visibleReplyLimits[$comment->id] ?? 0);
    $childComments = $modalCommentsByParent->get($comment->id, collect());
@endphp

<div class="feed-reply feed-comment-node" wire:key="feed-comment-{{ $context }}-{{ $comment->id }}" style="--comment-depth: {{ $depth }};">
    <div class="feed-reply-main">
        <div class="feed-reply-meta">
            <strong>{{ $commentAuthorName }}</strong>
            <time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->diffForHumans() }}</time>
            @if(($comment->edits_count ?? 0) > 0 && ! $comment->isDeleted())
                <span class="feed-comment-edited-badge">ред.</span>
            @endif
        </div>

        @if($comment->isDeleted())
            <p class="feed-reply-deleted">Комментарий удален</p>
        @elseif($editingCommentId === $comment->id)
            <form class="feed-comment-form feed-inline-comment-form" wire:submit="updateComment">
                <input type="text" wire:model="editingCommentBody" maxlength="1000">
                <button type="submit">Сохранить</button>
                <button type="button" wire:click="cancelEditingComment">Отмена</button>
            </form>
        @else
            <p>{{ $comment->body }}</p>

            @if(($comment->edits_count ?? 0) > 0)
                <div class="feed-comment-history">
                    <span>Старая версия</span>
                    <p>{{ $comment->edits->first()->body }}</p>
                </div>
            @endif
        @endif

        <div class="feed-comment-actions">
            <button class="feed-comment-vote {{ $commentVote === \App\Models\FeedVote::VALUE_UP ? 'active-up' : '' }}" type="button" wire:click="voteComment({{ $comment->id }}, '{{ \App\Models\FeedVote::VALUE_UP }}')" @disabled($comment->isDeleted())>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m7 14 5-5 5 5"/>
                </svg>
                {{ $comment->up_votes_count }}
            </button>
            <button class="feed-comment-vote {{ $commentVote === \App\Models\FeedVote::VALUE_DOWN ? 'active-down' : '' }}" type="button" wire:click="voteComment({{ $comment->id }}, '{{ \App\Models\FeedVote::VALUE_DOWN }}')" @disabled($comment->isDeleted())>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m7 10 5 5 5-5"/>
                </svg>
                {{ $comment->down_votes_count }}
            </button>

            @unless($comment->isDeleted())
                <button type="button" wire:click="startReplyingToComment({{ $comment->id }})">Ответить</button>

                @if($comment->user_id === auth()->id())
                    <button type="button" wire:click="startEditingComment({{ $comment->id }})">Редактировать</button>
                    <button type="button" wire:click="deleteComment({{ $comment->id }})">Удалить</button>
                @endif
            @endunless
        </div>

        @if($replyToCommentId === $comment->id)
            <form class="feed-comment-form feed-inline-comment-form" wire:submit="createCommentReply">
                <input type="text" wire:model="replyBody" maxlength="1000" placeholder="Ответить {{ $commentAuthorName }}">
                <button type="submit">Ответить</button>
                <button type="button" wire:click="cancelCommentReply">Отмена</button>
            </form>
        @endif

        @if($replyCount > 0 && $visibleReplyLimit < $replyCount)
            <button class="feed-replies-toggle" type="button" wire:click="showMoreCommentReplies({{ $comment->id }})">
                @if($visibleReplyLimit === 0)
                    ответы {{ $replyCount }}
                @else
                    показать еще {{ max(0, $replyCount - $visibleReplyLimit) }}
                @endif
            </button>
        @endif
    </div>

    @if($visibleReplyLimit > 0)
        <div class="feed-reply-children">
            @foreach($childComments->take($visibleReplyLimit) as $childComment)
                @include('livewire.partials.feed-comment', [
                    'comment' => $childComment,
                    'context' => $context,
                    'depth' => $depth + 1,
                    'modalCommentsByParent' => $modalCommentsByParent,
                    'modalReplyCounts' => $modalReplyCounts,
                    'visibleReplyLimits' => $visibleReplyLimits,
                ])
            @endforeach
        </div>
    @endif
</div>
