<?php

namespace App\Livewire;

use App\Models\Bookmark;
use App\Models\FeedPost;
use Illuminate\Contracts\View\View;
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
        $deletedCount = Bookmark::query()->where('user_id', $userId)->where('original_deleted', true)->count();

        return view('livewire.bookmarks', [
            'bookmarks' => $bookmarks,
            'totalCount' => $total,
            'deletedCount' => $deletedCount,
            'hasMore' => $total > $this->perPage,
        ]);
    }
}
