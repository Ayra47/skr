<?php

namespace App\Livewire;

use App\Models\FeedComment;
use App\Models\FeedCommentVote;
use App\Models\FeedPost;
use App\Models\FeedVote;
use App\Notifications\FeedCommentNotification;
use App\Notifications\FeedVoteNotification;
use App\Services\FeedAttachmentThumbnail;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Feed extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'tab', except: 'friends')]
    public string $tab = 'friends';

    public string $body = '';

    public string $visibility = FeedPost::VISIBILITY_FRIENDS;

    public string $expiresIn = FeedPost::EXPIRES_24H;

    public bool $isWhisper = false;

    /**
     * @var array<int, mixed>
     */
    public array $attachments = [];

    #[Url(as: 'post', except: null)]
    public ?int $modalPostId = null;

    public ?int $replyToCommentId = null;

    public string $replyBody = '';

    public ?int $editingCommentId = null;

    public string $editingCommentBody = '';

    /**
     * @var array<int, string>
     */
    public array $commentBodies = [];

    /**
     * @var array<int, bool>
     */
    public array $expandedCommentPosts = [];

    /**
     * @var array<int, int>
     */
    public array $visibleReplyLimits = [];

    /**
     * @var array<int, int>
     */
    public array $previewCommentLimits = [];

    /**
     * @var array<int, array<int, int>>
     */
    public array $previewCommentOrders = [];

    /**
     * @var array<int, array<int, int>>
     */
    public array $previewReplyOrders = [];

    /**
     * @var array<int, array<int, int>>
     */
    public array $modalCommentOrders = [];

    public function mount(): void
    {
        if (! in_array($this->tab, ['friends', 'all', 'mine'], strict: true)) {
            $this->tab = 'friends';
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['friends', 'all', 'mine'], strict: true)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
    }

    public function createPost(): void
    {
        $this->body = trim($this->body);

        $validated = $this->validate($this->postRules());

        $validated['expires_at'] = FeedPost::expiresAtFor($validated['expiresIn']);
        unset($validated['attachments'], $validated['expiresIn'], $validated['isWhisper']);

        DB::transaction(function () use ($validated): void {
            $post = FeedPost::query()->create([
                ...$validated,
                'body' => filled($validated['body'] ?? null) ? trim($validated['body']) : null,
                'user_id' => Auth::id(),
                'is_whisper' => $this->isWhisper,
            ]);

            $thumbnailer = app(FeedAttachmentThumbnail::class);
            $attachmentRows = collect($this->attachments)
                ->map(function ($attachment, int $index) use ($post, $thumbnailer): array {
                    $name = $this->attachmentName($attachment->getClientOriginalName());
                    $mime = $attachment->getMimeType();
                    $size = $attachment->getSize();
                    $thumbnailPath = $thumbnailer->store($attachment);

                    return [
                        'feed_post_id' => $post->id,
                        'path' => $attachment->store('feed-attachments', 'local'),
                        'thumbnail_path' => $thumbnailPath,
                        'name' => $name,
                        'mime' => $mime,
                        'size' => $size,
                        'position' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })
                ->all();

            if ($attachmentRows !== []) {
                $post->attachments()->insert($attachmentRows);
            }
        });

        $this->reset(['body', 'attachments']);
        $this->visibility = FeedPost::VISIBILITY_FRIENDS;
        $this->expiresIn = FeedPost::EXPIRES_24H;
        $this->isWhisper = false;
        $this->resetPage();
        $this->dispatch('feed-post-created');
    }

    public function vote(int $postId, string $value): void
    {
        validator(['value' => $value], [
            'value' => ['required', Rule::in([FeedVote::VALUE_UP, FeedVote::VALUE_DOWN])],
        ])->validate();

        $post = FeedPost::query()->findOrFail($postId);
        $user = Auth::user();
        abort_unless($post->isVisibleTo($user), 403);

        $vote = FeedVote::query()->firstOrNew([
            'feed_post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        if ($vote->exists && $vote->value === $value) {
            $vote->delete();

            return;
        }

        $vote->value = $value;
        $vote->save();

        if ($post->user_id !== $user->id && $post->author) {
            $post->author->notifications()
                ->where('type', FeedVoteNotification::class)
                ->where('data->voter_id', (string) $user->id)
                ->where('data->post_id', (string) $post->id)
                ->delete();

            $post->author->notify(new FeedVoteNotification($user, $post, $value));
        }
    }

    public function createComment(int $postId): void
    {
        $body = trim($this->commentBodies[$postId] ?? '');
        $this->commentBodies[$postId] = $body;

        $this->validate([
            "commentBodies.{$postId}" => ['required', 'string', 'max:1000'],
        ]);

        $post = FeedPost::query()->findOrFail($postId);
        abort_unless($post->isVisibleTo(Auth::user()), 403);

        $user = Auth::user();

        $comment = $post->comments()->create([
            'user_id' => $user->id,
            'body' => $body,
        ]);

        if ($post->user_id !== $user->id && $post->author) {
            $post->author->notify(new FeedCommentNotification($user, $post, $comment));
        }

        $this->commentBodies[$postId] = '';
    }

    public function startReplyingToComment(int $commentId): void
    {
        $comment = $this->findVisibleComment($commentId);

        $this->replyToCommentId = $comment->id;
        $this->replyBody = '';
        $this->visibleReplyLimits[$comment->id] ??= 5;
    }

    public function cancelCommentReply(): void
    {
        $this->replyToCommentId = null;
        $this->replyBody = '';
    }

    public function createCommentReply(): void
    {
        $body = trim($this->replyBody);
        $this->replyBody = $body;

        $this->validate([
            'replyBody' => ['required', 'string', 'max:1000'],
        ]);

        $parent = $this->findVisibleComment((int) $this->replyToCommentId);

        $user = Auth::user();

        $comment = $parent->post->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'body' => $body,
        ]);

        if ($parent->post->user_id !== $user->id && $parent->post->author) {
            $parent->post->author->notify(new FeedCommentNotification($user, $parent->post, $comment));
        }

        $this->visibleReplyLimits[$parent->id] ??= 5;
        $this->cancelCommentReply();
    }

    public function startEditingComment(int $commentId): void
    {
        $comment = $this->findVisibleComment($commentId);
        abort_unless($comment->user_id === Auth::id() && ! $comment->isDeleted(), 403);

        $this->editingCommentId = $comment->id;
        $this->editingCommentBody = $comment->body;
    }

    public function cancelEditingComment(): void
    {
        $this->editingCommentId = null;
        $this->editingCommentBody = '';
    }

    public function updateComment(): void
    {
        $body = trim($this->editingCommentBody);
        $this->editingCommentBody = $body;

        $this->validate([
            'editingCommentBody' => ['required', 'string', 'max:1000'],
        ]);

        $comment = $this->findVisibleComment((int) $this->editingCommentId);
        abort_unless($comment->user_id === Auth::id() && ! $comment->isDeleted(), 403);

        if ($comment->body === $body) {
            $this->cancelEditingComment();

            return;
        }

        $comment->edits()->create([
            'body' => $comment->body,
            'created_at' => now(),
        ]);
        $comment->update(['body' => $body]);
        $this->cancelEditingComment();
    }

    public function deleteComment(int $commentId): void
    {
        $comment = $this->findVisibleComment($commentId);
        abort_unless($comment->user_id === Auth::id() && ! $comment->isDeleted(), 403);

        $comment->update(['deleted_at' => now()]);

        if ($this->editingCommentId === $comment->id) {
            $this->cancelEditingComment();
        }

        if ($this->replyToCommentId === $comment->id) {
            $this->cancelCommentReply();
        }
    }

    public function voteComment(int $commentId, string $value): void
    {
        validator(['value' => $value], [
            'value' => ['required', Rule::in([FeedVote::VALUE_UP, FeedVote::VALUE_DOWN])],
        ])->validate();

        $comment = $this->findVisibleComment($commentId);
        abort_unless(! $comment->isDeleted(), 403);

        $vote = FeedCommentVote::query()->firstOrNew([
            'feed_comment_id' => $comment->id,
            'user_id' => Auth::id(),
        ]);

        if ($vote->exists && $vote->value === $value) {
            $vote->delete();

            return;
        }

        $vote->value = $value;
        $vote->save();
    }

    public function showMoreCommentReplies(int $commentId): void
    {
        $comment = $this->findVisibleComment($commentId);

        $this->visibleReplyLimits[$comment->id] = ($this->visibleReplyLimits[$comment->id] ?? 0) + 5;
    }

    public function loadMorePostComments(int $postId): void
    {
        $post = FeedPost::query()->findOrFail($postId);
        abort_unless($post->isVisibleTo(Auth::user()), 403);

        if (! ($this->expandedCommentPosts[$postId] ?? false)) {
            return;
        }

        $this->previewCommentLimits[$postId] = ($this->previewCommentLimits[$postId] ?? 3) + 10;
    }

    public function toggleComments(int $postId): void
    {
        $post = FeedPost::query()->findOrFail($postId);
        abort_unless($post->isVisibleTo(Auth::user()), 403);

        if ($this->expandedCommentPosts[$postId] ?? false) {
            unset($this->expandedCommentPosts[$postId]);

            return;
        }

        $this->expandedCommentPosts[$postId] = true;
        $this->previewCommentLimits[$postId] ??= 3;
    }

    public function openPost(int $postId): void
    {
        $post = FeedPost::query()->findOrFail($postId);
        abort_unless($post->isVisibleTo(Auth::user()), 403);

        $this->modalPostId = $post->id;
        $this->replyToCommentId = null;
        $this->replyBody = '';
        $this->editingCommentId = null;
        $this->editingCommentBody = '';
        $this->modalCommentOrders = [];
    }

    public function closePost(): void
    {
        $this->modalPostId = null;
        $this->replyToCommentId = null;
        $this->replyBody = '';
        $this->editingCommentId = null;
        $this->editingCommentBody = '';
        $this->modalCommentOrders = [];
    }

    public function removeAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->attachments)) {
            return;
        }

        array_splice($this->attachments, $index, 1);
        $this->dispatch('feed-attachment-removed', index: $index);
    }

    public function render(): View
    {
        $user = Auth::user();
        $friendIds = $user->friendIds();
        $posts = FeedPost::query()
            ->with([
                'attachments' => fn ($query) => $query->orderBy('position'),
                'author',
                'votes' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount([
                'comments',
                'comments as root_comments_count' => fn ($query) => $query->whereNull('parent_id'),
                'votes as up_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_UP),
                'votes as down_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_DOWN),
            ])
            ->live()
            ->forTab($user, $this->tab, $friendIds)
            ->latest()
            ->simplePaginate(25);

        $postIds = collect($posts->items())->pluck('id')->all();
        $expandedPostIds = collect(array_keys($this->expandedCommentPosts))
            ->map(fn (int|string $id) => (int) $id)
            ->intersect($postIds)
            ->values()
            ->all();

        $topComments = FeedComment::query()
            ->with([
                'edits',
                'user',
                'votes' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount([
                'edits',
                'replies',
                'votes as comment_reactions_count',
                'votes as up_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_UP),
                'votes as down_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_DOWN),
            ])
            ->whereIn('feed_post_id', $postIds)
            ->whereNull('parent_id')
            ->orderByDesc('comment_reactions_count')
            ->orderBy('created_at')
            ->get()
            ->groupBy('feed_post_id')
            ->map(fn (Collection $comments) => $comments->first());

        $previewTreeComments = FeedComment::query()
            ->with([
                'edits',
                'user',
                'votes' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount([
                'edits',
                'replies',
                'votes as comment_reactions_count',
                'votes as up_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_UP),
                'votes as down_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_DOWN),
            ])
            ->whereIn('feed_post_id', $expandedPostIds)
            ->orderByDesc('comment_reactions_count')
            ->orderBy('created_at')
            ->get();

        $previewCommentsByParent = $previewTreeComments
            ->groupBy(fn (FeedComment $comment) => $comment->parent_id ?? 0)
            ->map(function (Collection $comments, int|string $parentId) {
                $parentId = (int) $parentId;
                $this->previewReplyOrders[$parentId] ??= $comments->pluck('id')->map(fn (int|string $id) => (int) $id)->all();

                return $this->sortCommentsByStoredOrder($comments, $this->previewReplyOrders[$parentId]);
            });
        $previewReplyCounts = $previewTreeComments->whereNotNull('parent_id')->countBy('parent_id');
        $previewComments = $previewTreeComments
            ->whereNull('parent_id')
            ->groupBy('feed_post_id')
            ->map(function (Collection $comments, int|string $postId) {
                $postId = (int) $postId;
                $this->previewCommentOrders[$postId] ??= $comments->pluck('id')->map(fn (int|string $id) => (int) $id)->all();

                return $this->sortCommentsByStoredOrder($comments, $this->previewCommentOrders[$postId])
                    ->take($this->previewCommentLimits[$postId] ?? 3);
            });

        $modalPost = null;
        $modalCommentsByParent = collect();
        $modalReplyCounts = collect();
        if ($this->modalPostId !== null) {
            $modalPost = FeedPost::query()
                ->with([
                    'attachments' => fn ($query) => $query->orderBy('position'),
                    'author',
                ])
                ->withCount([
                    'comments',
                    'votes as up_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_UP),
                    'votes as down_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_DOWN),
                ])
                ->find($this->modalPostId);

            if (! $modalPost || ! $modalPost->isVisibleTo($user, $friendIds)) {
                $this->modalPostId = null;
                $modalPost = null;
            }

            if ($modalPost) {
                $modalComments = FeedComment::query()
                    ->where('feed_post_id', $modalPost->id)
                    ->with([
                        'edits',
                        'user',
                        'votes' => fn ($query) => $query->where('user_id', $user->id),
                    ])
                    ->withCount([
                        'edits',
                        'replies',
                        'votes as comment_reactions_count',
                        'votes as up_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_UP),
                        'votes as down_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_DOWN),
                    ])
                    ->orderByDesc('comment_reactions_count')
                    ->orderBy('created_at')
                    ->get();

                $modalCommentsByParent = $modalComments
                    ->groupBy(fn (FeedComment $comment) => $comment->parent_id ?? 0)
                    ->map(function (Collection $comments, int|string $parentId) {
                        $parentId = (int) $parentId;
                        $this->modalCommentOrders[$parentId] ??= $comments->pluck('id')->map(fn (int|string $id) => (int) $id)->all();

                        return $this->sortCommentsByStoredOrder($comments, $this->modalCommentOrders[$parentId]);
                    });
                $modalReplyCounts = $modalComments->whereNotNull('parent_id')->countBy('parent_id');
            }
        }

        return view('livewire.feed', [
            'modalCommentsByParent' => $modalCommentsByParent,
            'modalPost' => $modalPost,
            'modalReplyCounts' => $modalReplyCounts,
            'posts' => $posts,
            'previewComments' => $previewComments,
            'previewCommentsByParent' => $previewCommentsByParent,
            'previewReplyCounts' => $previewReplyCounts,
            'topComments' => $topComments,
            'user' => $user,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postRules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachments'],
            'visibility' => ['required', Rule::in(FeedPost::visibilityValues())],
            'expiresIn' => ['required', Rule::in(FeedPost::expirationValues())],
            'isWhisper' => ['boolean'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => [
                'extensions:jpg,jpeg,png,gif,webp,mp4,webm,mov,pdf,txt,md,zip',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov', 'pdf', 'txt', 'md', 'zip'])
                    ->max(500 * 1024),
            ],
        ];
    }

    private function attachmentName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN ._\-]/u', '_', $name) ?: 'attachment';

        return Str::limit($name, 120, '');
    }

    private function findVisibleComment(int $commentId): FeedComment
    {
        $comment = FeedComment::query()
            ->with('post')
            ->findOrFail($commentId);

        abort_unless($comment->post->isVisibleTo(Auth::user()), 403);

        return $comment;
    }

    /**
     * @param  array<int, int>  $orderedIds
     * @return Collection<int, FeedComment>
     */
    private function sortCommentsByStoredOrder(Collection $comments, array $orderedIds): Collection
    {
        $positions = array_flip($orderedIds);

        return $comments
            ->sortBy(fn (FeedComment $comment) => $positions[$comment->id] ?? PHP_INT_MAX)
            ->values();
    }
}
