<?php

namespace App\Http\Controllers;

use App\Events\FriendRequestAcceptedEvent;
use App\Models\Friend;
use App\Models\FriendCode;
use App\Models\FriendRequest;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FriendsController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $allFriends = $user->friends->merge($user->friendOf)->unique('id');
        $allFriends->load('userKey');

        $activeCode = $user->activeFriendCode();
        $pendingRequests = $user->receivedFriendRequests()
            ->pending()
            ->with('sender')
            ->latest()
            ->get();

        $unreadCount = $user->unreadFriendRequestsCount();

        return view('pages.friends.index', compact('allFriends', 'activeCode', 'pendingRequests', 'unreadCount'));
    }

    public function createCode(): JsonResponse
    {
        $user = Auth::user();

        // Block any existing active codes
        $user->friendCodes()->active()->update([
            'is_blocked' => true,
        ]);

        // Create new code
        $code = FriendCode::create([
            'user_id' => $user->id,
            'code' => FriendCode::generateCode(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json([
            'success' => true,
            'code' => $code->code,
            'expires_at' => $code->expires_at->toIso8601String(),
        ]);
    }

    public function searchByCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:10',
        ]);

        $code = FriendCode::where('code', $request->code)
            ->active()
            ->with('user')
            ->first();

        if (! $code) {
            return response()->json([
                'success' => false,
                'message' => 'Код не найден или истёк',
            ]);
        }

        $user = Auth::user();

        // Check if already friends
        if ($user->isFriendWith($code->user_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже друзья с этим пользователем',
            ]);
        }

        // Check if already sent request
        $existingRequest = FriendRequest::where('sender_id', $user->id)
            ->where('receiver_id', $code->user_id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже отправили запрос этому пользователю',
            ]);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $code->user->id,
                'login' => $code->user->login,
            ],
        ]);
    }

    public function sendRequest(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:10',
        ]);

        $user = Auth::user();

        $code = FriendCode::where('code', $request->code)
            ->active()
            ->first();

        if (! $code) {
            return response()->json([
                'success' => false,
                'message' => 'Код не найден или истёк',
            ]);
        }

        // Check if already friends
        if ($user->isFriendWith($code->user_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже друзья с этим пользователем',
            ]);
        }

        // Check if already sent request
        $existingRequest = FriendRequest::where('sender_id', $user->id)
            ->where('receiver_id', $code->user_id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже отправили запрос этому пользователю',
            ]);
        }

        // Block the code after first use
        $code->update(['is_blocked' => true]);

        // Create friend request
        $friendRequest = FriendRequest::create([
            'sender_id' => $user->id,
            'receiver_id' => $code->user_id,
            'friend_code_id' => $code->id,
            'status' => 'pending',
        ]);

        $code->user->notify(new FriendRequestNotification($user, $friendRequest));

        return response()->json([
            'success' => true,
            'message' => 'Запрос в друзья отправлен',
        ]);
    }

    public function joinByCode(string $code): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect('/');
        }

        $user = Auth::user();

        $friendCode = FriendCode::where('code', $code)->active()->first();

        if (! $friendCode) {
            return redirect()->route('friends.index')->with('join_error', 'Код не найден или уже истёк.');
        }

        if ($friendCode->user_id === $user->id) {
            return redirect()->route('friends.index')->with('join_error', 'Нельзя добавить самого себя.');
        }

        if ($user->isFriendWith($friendCode->user_id)) {
            return redirect()->route('friends.index')->with('join_error', 'Вы уже друзья с этим пользователем.');
        }

        $alreadySent = FriendRequest::where('sender_id', $user->id)
            ->where('receiver_id', $friendCode->user_id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadySent) {
            return redirect()->route('friends.index')->with('join_error', 'Вы уже отправили запрос этому пользователю.');
        }

        $friendCode->update(['is_blocked' => true]);

        $friendRequest = FriendRequest::create([
            'sender_id' => $user->id,
            'receiver_id' => $friendCode->user_id,
            'friend_code_id' => $friendCode->id,
            'status' => 'pending',
        ]);

        $friendCode->user->notify(new FriendRequestNotification($user, $friendRequest));

        return redirect()->route('friends.index')->with('join_success', 'Запрос в друзья отправлен.');
    }

    public function acceptRequest(Request $request): JsonResponse
    {
        $request->validate([
            'request_id' => 'required|integer|exists:friend_requests,id',
        ]);

        $user = Auth::user();

        $friendRequest = FriendRequest::where('id', $request->request_id)
            ->where('receiver_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (! $friendRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Запрос не найден',
            ]);
        }

        DB::beginTransaction();
        try {
            // Add friendship (bidirectional)
            Friend::create([
                'user_id' => $friendRequest->sender_id,
                'friend_id' => $user->id,
            ]);

            Friend::create([
                'user_id' => $user->id,
                'friend_id' => $friendRequest->sender_id,
            ]);

            // Update request status
            $friendRequest->update(['status' => 'accepted']);

            // Block the code if it exists
            if ($friendRequest->friend_code_id) {
                FriendCode::where('id', $friendRequest->friend_code_id)
                    ->update(['is_blocked' => true, 'is_used' => true, 'used_at' => now()]);
            }

            DB::commit();

            // Send notification and broadcast event to sender
            $sender = User::find($friendRequest->sender_id);
            if ($sender) {
                $sender->notify(new FriendRequestAccepted($user));
                broadcast(new FriendRequestAcceptedEvent(
                    $sender->id,
                    $user->id,
                    $user->login
                ));
            }

            return response()->json([
                'success' => true,
                'message' => 'Запрос принят',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при принятии запроса',
            ], 500);
        }
    }

    public function declineRequest(Request $request): JsonResponse
    {
        $request->validate([
            'request_id' => 'required|integer|exists:friend_requests,id',
        ]);

        $user = Auth::user();

        $friendRequest = FriendRequest::where('id', $request->request_id)
            ->where('receiver_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (! $friendRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Запрос не найден',
            ]);
        }

        $friendRequest->update(['status' => 'declined']);

        // Unblock the code so it can be used again
        if ($friendRequest->friend_code_id) {
            FriendCode::where('id', $friendRequest->friend_code_id)
                ->update(['is_blocked' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Запрос отклонён',
        ]);
    }

    public function removeFriend(Request $request): JsonResponse
    {
        $request->validate([
            'friend_id' => 'required|integer|exists:users,id',
        ]);

        $user = Auth::user();

        // Remove both directions
        Friend::where('user_id', $user->id)
            ->where('friend_id', $request->friend_id)
            ->delete();

        Friend::where('user_id', $request->friend_id)
            ->where('friend_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Друг удалён',
        ]);
    }

    public function markAsRead(Request $request): JsonResponse
    {
        $user = Auth::user();

        $user->receivedFriendRequests()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
        ]);
    }

    public function getUnreadCount(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'count' => $user->unreadFriendRequestsCount(),
        ]);
    }

    public function syncTime(): Response
    {
        return response()->noContent();
    }
}
