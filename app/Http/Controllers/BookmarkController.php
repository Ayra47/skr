<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\BookmarkAttachment;
use App\Models\FeedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookmarkController extends Controller
{
    public function index(): View
    {
        return view('pages.bookmarks.index');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'bookmarkable_type' => ['required', 'string'],
            'bookmarkable_id' => ['required', 'integer'],
        ]);

        $user = Auth::user();
        $type = $request->input('bookmarkable_type');
        $id = (int) $request->input('bookmarkable_id');

        // community_post is reserved for future use; reject until community tables exist.
        if ($type === 'community_post') {
            abort(422, 'community_post bookmarks are not yet supported');
        }

        $morphMap = [
            'feed_post' => FeedPost::class,
        ];

        abort_unless(array_key_exists($type, $morphMap), 422);

        $modelClass = $morphMap[$type];
        $morphType = $modelClass;
        $key = (string) $id;

        // Prefer key-based lookup (matches unique index). Fallback to bookmarkable_id
        // for pre-migration rows where bookmarkable_key was not yet set.
        $existing = Bookmark::query()
            ->where('user_id', $user->id)
            ->where('bookmarkable_type', $morphType)
            ->where(function ($q) use ($key, $id): void {
                $q->where('bookmarkable_key', $key)
                    ->orWhere(function ($q2) use ($id): void {
                        $q2->whereNull('bookmarkable_key')->where('bookmarkable_id', $id);
                    });
            })
            ->first();

        if ($existing) {
            return response()->json(['bookmarked' => true, 'id' => $existing->id]);
        }

        $model = $modelClass::query()->findOrFail($id);

        $bookmark = DB::transaction(function () use ($user, $model, $morphType, $id, $key, $type): Bookmark {
            $bookmark = Bookmark::query()->create([
                'user_id' => $user->id,
                'bookmarkable_type' => $morphType,
                'bookmarkable_id' => $id,
                'bookmarkable_key' => $key,
                'snapshot_body' => $model->body ?? null,
                'snapshot_author_id' => $model->is_whisper ? null : ($model->user_id ?? null),
                'snapshot_author_name' => $model->is_whisper ? null : ($model->author?->feedName() ?? null),
                'snapshot_is_whisper' => $model->is_whisper ?? false,
                'snapshot_posted_at' => $model->created_at,
                'source_label' => $this->sourceLabelFor($type),
                'original_deleted' => false,
            ]);

            if ($model instanceof FeedPost) {
                $attachmentRows = $model->attachments()
                    ->orderBy('position')
                    ->get()
                    ->filter(fn ($att) => $att->existsOnDisk())
                    ->map(function ($att) use ($bookmark): array {
                        $ext = pathinfo($att->path, PATHINFO_EXTENSION);
                        $newPath = 'bookmarks/'.$bookmark->id.'/'.$att->position.($ext ? '.'.$ext : '');
                        Storage::disk('local')->copy($att->path, $newPath);

                        $newThumbPath = null;
                        if (filled($att->thumbnail_path) && Storage::disk('local')->exists($att->thumbnail_path)) {
                            $thumbExt = pathinfo($att->thumbnail_path, PATHINFO_EXTENSION);
                            $newThumbPath = 'bookmarks/'.$bookmark->id.'/'.$att->position.'_thumb'.($thumbExt ? '.'.$thumbExt : '');
                            Storage::disk('local')->copy($att->thumbnail_path, $newThumbPath);
                        }

                        return [
                            'bookmark_id' => $bookmark->id,
                            'path' => $newPath,
                            'thumbnail_path' => $newThumbPath,
                            'name' => $att->name,
                            'mime' => $att->mime,
                            'size' => $att->size,
                            'position' => $att->position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })
                    ->values()
                    ->all();

                if ($attachmentRows !== []) {
                    BookmarkAttachment::query()->insert($attachmentRows);
                }
            }

            return $bookmark;
        });

        return response()->json(['bookmarked' => true, 'id' => $bookmark->id], 201);
    }

    public function destroy(Bookmark $bookmark): Response
    {
        abort_unless($bookmark->user_id === Auth::id(), 403);

        $bookmark->attachments->each(function (BookmarkAttachment $att): void {
            Storage::disk('local')->delete($att->path);
        });

        $bookmark->delete();

        return response()->noContent();
    }

    public function attachment(Bookmark $bookmark, BookmarkAttachment $attachment, Request $request): StreamedResponse
    {
        abort_unless($bookmark->user_id === Auth::id(), 403);
        abort_unless($attachment->bookmark_id === $bookmark->id, 404);

        if ($request->boolean('thumbnail') && filled($attachment->thumbnail_path) && Storage::disk('local')->exists($attachment->thumbnail_path)) {
            return Storage::disk('local')->response($attachment->thumbnail_path, $attachment->downloadName());
        }

        abort_unless($attachment->existsOnDisk(), 404);

        if ($attachment->isImage() || $attachment->isVideo()) {
            return Storage::disk('local')->response($attachment->path, $attachment->downloadName());
        }

        return Storage::disk('local')->download($attachment->path, $attachment->downloadName());
    }

    private function sourceLabelFor(string $type): string
    {
        return match ($type) {
            'feed_post' => 'из ленты',
            default => '',
        };
    }
}
