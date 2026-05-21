<?php

namespace App\Livewire;

use App\Models\Bookmark;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\FeedPost;
use App\Services\FeedCard;
use App\Services\FeedVisibilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class Bookmarks extends Component
{
    public string $tab = 'all';

    public string $search = '';

    public int $perPage = 10;

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->perPage = 10;
    }

    public function updatedSearch(): void
    {
        $this->perPage = 10;
    }

    public function loadMore(): void
    {
        $this->perPage += 20;
    }

    public function remove(int $bookmarkId): void
    {
        $bookmark = Bookmark::query()
            ->where('user_id', Auth::id())
            ->with('attachments')
            ->findOrFail($bookmarkId);

        $bookmark->attachments->each(fn ($att) => Storage::disk('local')->delete($att->path));

        $bookmark->delete();
    }

    public function render(): View
    {
        $userId = Auth::id();

        $query = Bookmark::query()
            ->where('user_id', $userId)
            ->with('attachments')
            ->latest();

        match ($this->tab) {
            'feed_post' => $query
                ->where('bookmarkable_type', FeedPost::class)
                ->where('snapshot_is_whisper', false),
            'whisper' => $query
                ->where('bookmarkable_type', FeedPost::class)
                ->where('snapshot_is_whisper', true),
            'community_post' => $query->where('bookmarkable_type', CommunityPost::class),
            default => null,
        };

        if (filled($this->search)) {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('snapshot_body', 'like', $term)
                    ->orWhere('snapshot_author_name', 'like', $term);
            });
        }

        $total = $query->count();
        $bookmarks = $query->take($this->perPage)->get();
        $this->hydrateCommunityBookmarks($bookmarks);

        $deletedCount = Bookmark::query()->where('user_id', $userId)->where('original_deleted', true)->count();

        return view('livewire.bookmarks', [
            'bookmarks' => $bookmarks,
            'totalCount' => $total,
            'deletedCount' => $deletedCount,
            'hasMore' => $total > $this->perPage,
        ]);
    }

    /**
     * @param  EloquentCollection<int, Bookmark>  $bookmarks
     */
    private function hydrateCommunityBookmarks(EloquentCollection $bookmarks): void
    {
        $communityBookmarks = $bookmarks
            ->filter(fn (Bookmark $bookmark): bool => $bookmark->bookmarkable_type === CommunityPost::class);

        if ($communityBookmarks->isEmpty()) {
            return;
        }

        $posts = CommunityPost::withTrashed()
            ->with(['author', 'community', 'topic'])
            ->whereIn('id', $communityBookmarks->pluck('bookmarkable_key')->filter()->values())
            ->get()
            ->keyBy('id');

        $memberNames = CommunityMember::query()
            ->whereIn('community_id', $posts->pluck('community_id')->filter()->unique()->values())
            ->whereIn('user_id', $posts->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy(fn (CommunityMember $member): string => $member->community_id.':'.$member->user_id);

        $viewer = Auth::user();
        $visibility = app(FeedVisibilityService::class);

        $communityBookmarks->each(function (Bookmark $bookmark) use ($posts, $memberNames, $viewer, $visibility): void {
            $post = $posts->get($bookmark->bookmarkable_key);

            if (! $post || $post->trashed()) {
                $bookmark->forceFill(['original_deleted' => true])->save();
                $bookmark->setAttribute('community_bookmark_unavailable', true);

                return;
            }

            if (! $visibility->canViewerSeeCommunityPost($viewer, $post, 'bookmark')) {
                $bookmark->forceFill(['access_revoked' => true])->save();
                $bookmark->setAttribute('community_bookmark_unavailable', true);

                return;
            }

            if ($bookmark->access_revoked) {
                $bookmark->forceFill(['access_revoked' => false])->save();
            }

            $member = $memberNames->get($post->community_id.':'.$post->user_id);
            $authorDisplayName = $post->community?->hide_real_names
                ? ($member?->pseudonym ?: $member?->community_display_name ?: 'member #'.$post->user_id)
                : ($post->author?->feedName() ?: 'member #'.$post->user_id);

            $bookmark->setAttribute(
                'community_post_card',
                new FeedCard(
                    type: FeedCard::TYPE_COMMUNITY_POST,
                    sourceId: (string) $post->id,
                    actorId: $post->user_id,
                    sortAt: $post->created_at,
                    communityPost: $post,
                    communityName: $post->community?->name,
                    communityVisibility: $post->community?->visibility,
                    topicName: $post->topic?->name,
                    authorDisplayName: $authorDisplayName,
                ),
            );
            $bookmark->setAttribute('community_bookmark_unavailable', false);
        });
    }
}
