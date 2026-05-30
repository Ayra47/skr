<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedCommentRequest;
use App\Http\Requests\StoreFeedPostRequest;
use App\Http\Requests\VoteFeedPostRequest;
use App\Models\FeedPost;
use App\Models\FeedPostAttachment;
use App\Models\FeedVote;
use App\Notifications\FeedCommentNotification;
use App\Notifications\FeedVoteNotification;
use App\Services\FeedAttachmentThumbnail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeedController extends Controller
{
    public function index(): View
    {
        return view('pages.feed.index');
    }

    public function store(StoreFeedPostRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $attachments = $request->file('attachments', []);

        $validated['expires_at'] = FeedPost::expiresAtFor($validated['expires_in']);

        unset($validated['attachments'], $validated['expires_in']);

        DB::transaction(function () use ($attachments, $validated): void {
            $post = FeedPost::query()->create([
                ...$validated,
                'body' => filled($validated['body'] ?? null) ? $validated['body'] : null,
                'user_id' => Auth::id(),
            ]);

            $thumbnailer = app(FeedAttachmentThumbnail::class);
            $attachmentRows = collect($attachments)
                ->map(function ($attachment, int $position) use ($post, $thumbnailer): array {
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
                        'position' => $position + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })
                ->all();

            if ($attachmentRows !== []) {
                $post->attachments()->insert($attachmentRows);
            }
        });

        return redirect()->route('feed.index');
    }

    public function vote(VoteFeedPostRequest $request, FeedPost $post): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($post->isVisibleTo($user), 403);

        $vote = FeedVote::query()->firstOrNew([
            'feed_post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        if ($vote->exists && $vote->value === $request->validated('value')) {
            $vote->delete();

            return back();
        }

        $vote->value = $request->validated('value');
        $vote->save();

        if ($post->user_id !== $user->id && $post->author) {
            $post->author->notify(new FeedVoteNotification($user, $post, $vote->value));
        }

        return back();
    }

    public function comment(StoreFeedCommentRequest $request, FeedPost $post): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($post->isVisibleTo($user), 403);

        $comment = $post->comments()->create([
            'user_id' => $user->id,
            'body' => $request->validated('body'),
        ]);

        if ($post->user_id !== $user->id && $post->author) {
            $post->author->notify(new FeedCommentNotification($user, $post, $comment));
        }

        return back();
    }

    public function attachment(Request $request, FeedPost $post, FeedPostAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->feed_post_id === $post->id, 404);
        abort_unless($post->isVisibleTo(Auth::user()), 403);
        abort_unless($attachment->existsOnDisk(), 404);

        if (
            $request->boolean('thumbnail')
            && $attachment->isImage()
            && filled($attachment->thumbnail_path)
            && Storage::disk('local')->exists($attachment->thumbnail_path)
        ) {
            return Storage::disk('local')->response($attachment->thumbnail_path, $attachment->downloadName());
        }

        if ($attachment->isImage() || $attachment->isVideo()) {
            return Storage::disk('local')->response($attachment->path, $attachment->downloadName());
        }

        return Storage::disk('local')->download($attachment->path, $attachment->downloadName());
    }

    public function destroy(FeedPost $post): JsonResponse
    {
        abort_unless($post->user_id === Auth::id(), 403);

        $post->delete();

        return response()->json(['success' => true]);
    }

    private function attachmentName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN ._\-]/u', '_', $name) ?: 'attachment';

        return Str::limit($name, 120, '');
    }
}
