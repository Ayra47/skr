<?php

namespace App\Livewire;

use App\Models\Bookmark;
use App\Models\FeedComment;
use App\Models\FeedCommentVote;
use App\Models\FeedPost;
use App\Models\FeedVote;
use App\Models\Poll;
use App\Notifications\FeedCommentNotification;
use App\Notifications\FeedVoteNotification;
use App\Services\FeedAttachmentThumbnail;
use App\Services\FeedItemsReader;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
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

    public bool $hasPoll = false;

    public string $pollMode = Poll::MODE_SINGLE;

    public ?int $pollMaxChoices = null;

    public string $pollClosesIn = 'never';

    public string $pollClosesAt = '';

    /**
     * @var array<int, string>
     */
    public array $pollOptions = ['', ''];

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

    public ?string $feedCursor = null;

    public ?string $nextFeedCursor = null;

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
        $this->feedCursor = null;
        $this->nextFeedCursor = null;
        $this->resetPage();
    }

    public function togglePoll(): void
    {
        $this->hasPoll = ! $this->hasPoll;

        if ($this->hasPoll) {
            $this->pollOptions = ['', ''];
            $this->pollMode = Poll::MODE_SINGLE;
            $this->pollMaxChoices = null;
            $this->pollClosesIn = 'never';
            $this->pollClosesAt = '';
        }
    }

    public function addPollOption(): void
    {
        if (count($this->pollOptions) >= 10) {
            return;
        }

        $this->pollOptions[] = '';
    }

    public function removePollOption(int $index): void
    {
        if (count($this->pollOptions) <= 2) {
            return;
        }

        array_splice($this->pollOptions, $index, 1);
        $this->pollOptions = array_values($this->pollOptions);
    }

    public function createPost(): void
    {
        $this->body = trim($this->body);

        $validated = $this->validate($this->postRules());

        $validated['expires_at'] = FeedPost::expiresAtFor($validated['expiresIn']);
        unset($validated['attachments'], $validated['expiresIn'], $validated['isWhisper']);

        $hasPoll = $this->hasPoll;
        $pollMode = $this->pollMode;
        $pollMaxChoices = $this->pollMaxChoices;
        $pollClosesIn = $this->pollClosesIn;
        $pollClosesAt = $this->pollClosesAt;
        $pollOptions = array_values(array_filter(array_map('trim', $this->pollOptions), 'strlen'));

        DB::transaction(function () use ($validated, $hasPoll, $pollMode, $pollMaxChoices, $pollClosesIn, $pollClosesAt, $pollOptions): void {
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

            if ($hasPoll && count($pollOptions) >= 2) {
                $closesAt = match ($pollClosesIn) {
                    '12h' => now()->addHours(12),
                    '24h' => now()->addDay(),
                    '7d' => now()->addDays(7),
                    'custom' => filled($pollClosesAt) ? Carbon::parse($pollClosesAt) : null,
                    default => null,
                };

                $poll = $post->poll()->create([
                    'mode' => $pollMode,
                    'max_choices' => $pollMode === Poll::MODE_MULTIPLE ? $pollMaxChoices : null,
                    'closes_at' => $closesAt,
                    'secret' => bin2hex(random_bytes(32)),
                ]);

                $optionRows = array_map(fn (string $text, int $pos) => [
                    'poll_id' => $poll->id,
                    'text' => $text,
                    'position' => $pos + 1,
                    'votes_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $pollOptions, array_keys($pollOptions));

                $poll->options()->insert($optionRows);
            }
        });

        $wasWhisper = $this->isWhisper;

        $this->reset(['body', 'attachments']);
        $this->hasPoll = false;
        $this->pollOptions = ['', ''];
        $this->pollMode = Poll::MODE_SINGLE;
        $this->pollMaxChoices = null;
        $this->pollClosesIn = 'never';
        $this->pollClosesAt = '';
        $this->visibility = FeedPost::VISIBILITY_FRIENDS;
        $this->expiresIn = FeedPost::EXPIRES_24H;
        $this->isWhisper = false;

        if ($wasWhisper) {
            $this->tab = 'all';
        }

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
        if (config('features.unified_feed_items_enabled')) {
            $posts = $this->loadUnifiedFeedPosts($user);
        } else {
            $posts = FeedPost::query()
                ->with([
                    'attachments' => fn ($query) => $query->orderBy('position'),
                    'author',
                    'votes' => fn ($query) => $query->where('user_id', $user->id),
                    'poll.options',
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
        }

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
                    'poll.options',
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

        $bookmarkIds = Bookmark::query()
            ->where('user_id', $user->id)
            ->where('bookmarkable_type', FeedPost::class)
            ->whereIn('bookmarkable_id', $postIds)
            ->pluck('id', 'bookmarkable_id')
            ->all();

        $pollVotedOptionIds = $this->loadPollVotedOptionIds(
            collect($posts->items())->pluck('poll')->filter()->values()->all(),
            $user->id
        );

        return view('livewire.feed', [
            'bookmarkIds' => $bookmarkIds,
            'modalCommentsByParent' => $modalCommentsByParent,
            'modalPost' => $modalPost,
            'modalReplyCounts' => $modalReplyCounts,
            'pollVotedOptionIds' => $pollVotedOptionIds,
            'posts' => $posts,
            'previewComments' => $previewComments,
            'previewCommentsByParent' => $previewCommentsByParent,
            'previewReplyCounts' => $previewReplyCounts,
            'topComments' => $topComments,
            'user' => $user,
        ]);
    }

    public function loadMoreFeed(): void
    {
        if (! config('features.unified_feed_items_enabled') || $this->nextFeedCursor === null) {
            return;
        }

        $this->feedCursor = $this->nextFeedCursor;
        $this->nextFeedCursor = null;
    }

    private function loadUnifiedFeedPosts(User $user): Paginator
    {
        $reader = app(FeedItemsReader::class);
        $feedPage = $reader->readForFeed($user, $this->tab, 25, $this->feedCursor);
        $this->nextFeedCursor = $feedPage->nextCursor;
        $postIds = $feedPage->feedPostIds();

        $ordered = FeedPost::query()
            ->with([
                'attachments' => fn ($query) => $query->orderBy('position'),
                'author',
                'votes' => fn ($query) => $query->where('user_id', $user->id),
                'poll.options',
            ])
            ->withCount([
                'comments',
                'comments as root_comments_count' => fn ($query) => $query->whereNull('parent_id'),
                'votes as up_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_UP),
                'votes as down_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_DOWN),
            ])
            ->whereIn('id', $postIds)
            ->get()
            ->sortBy(fn (FeedPost $post) => array_search($post->id, $postIds, strict: true))
            ->values();

        return new Paginator($ordered->all(), 25);
    }

    /**
     * @return array<string, mixed>
     */
    private function postRules(): array
    {
        $rules = [
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

        if ($this->hasPoll) {
            $rules['pollMode'] = ['required', Rule::in([Poll::MODE_SINGLE, Poll::MODE_MULTIPLE])];
            $rules['pollMaxChoices'] = ['nullable', 'integer', 'min:2', 'max:10'];
            $rules['pollClosesIn'] = ['required', Rule::in(['12h', '24h', '7d', 'custom', 'never'])];
            $rules['pollClosesAt'] = ['required_if:pollClosesIn,custom', 'nullable', 'date', 'after:now'];
            $rules['pollOptions'] = ['required', 'array', 'min:2', 'max:10'];
            $rules['pollOptions.*'] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }

    private function attachmentName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN ._\-]/u', '_', $name) ?: 'attachment';

        return Str::limit($name, 120, '');
    }

    /**
     * @param  array<int, Poll>  $polls
     * @return array<int, array<int>> keyed by poll_id
     */
    private function loadPollVotedOptionIds(array $polls, int $userId): array
    {
        if (empty($polls)) {
            return [];
        }

        $result = [];

        foreach ($polls as $poll) {
            $result[$poll->id] = $poll->votedOptionIds($userId);
        }

        return $result;
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
