<?php

namespace App\Http\Controllers;

use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationsController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->limit(50)->get();

        $items = $notifications->map(fn ($n) => $this->normalise($n));

        if ($request->wantsJson() || $request->query('json')) {
            return response()->json([
                'unread_count' => $notifications->whereNull('read_at')->count(),
                'items' => $items->values(),
            ]);
        }

        return view('pages.notifications.index');
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function normalise(object $n): array
    {
        $data = $n->data;

        // New-style notifications already carry type/title/body
        if (isset($data['type'], $data['title'])) {
            $result = [
                'id' => $n->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'body' => $data['body'] ?? '',
                'unread' => $n->read_at === null,
                'created_at' => $n->created_at->toIso8601String(),
            ];

            if (isset($data['post_id'])) {
                $result['post_id'] = $data['post_id'];
            }

            return $result;
        }

        // Legacy friend-request / accepted notifications
        $class = class_basename($n->type);

        $extra = [];

        [$type, $title, $subject, $body] = match ($class) {
            'FriendRequestNotification' => (function () use ($data, &$extra) {
                $requestId = $data['friend_request_id'] ?? null;
                $friendRequest = $requestId ? FriendRequest::find($requestId) : null;

                if ($friendRequest?->status === 'pending') {
                    $extra['friend_request_id'] = $requestId;
                } elseif ($friendRequest) {
                    $extra['friend_request_status'] = $friendRequest->status;
                }

                // Resolve pseudonym: prefer stored value, fall back to DB lookup
                $pseudonym = $data['sender_pseudonym']
                    ?? User::find($data['sender_id'] ?? 0)?->feedName()
                    ?? $data['sender_login']
                    ?? '?';

                $source = match ($data['source'] ?? 'code') {
                    'code' => 'через код',
                    default => 'запрос',
                };

                return [
                    'friend',
                    'Новый запрос в друзья',
                    $pseudonym,
                    'отправил запрос '.$source,
                ];
            })(),
            'FriendRequestAccepted' => (function () use ($data) {
                $pseudonym = User::find($data['receiver_id'] ?? 0)?->feedName()
                    ?? $data['receiver_login']
                    ?? '?';

                return ['friend', 'Запрос принят', $pseudonym, ''];
            })(),
            default => ['system', $data['message'] ?? $class, '', ''],
        };

        return [
            'id' => $n->id,
            'type' => $type,
            'title' => $title,
            'subject' => $subject,
            'body' => $body,
            'unread' => $n->read_at === null,
            'created_at' => $n->created_at->toIso8601String(),
            ...$extra,
        ];
    }
}
