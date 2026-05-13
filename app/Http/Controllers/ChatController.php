<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageEvent;
use App\Events\MessageDeletedEvent;
use App\Events\MessageDeliveredEvent;
use App\Events\MessageEditedEvent;
use App\Events\MessagePinnedEvent;
use App\Events\MessageReadEvent;
use App\Events\TypingEvent;
use App\Jobs\SendNewMessageEmailJob;
use App\Jobs\SendPushNotificationJob;
use App\Models\Conversation;
use App\Models\ConversationInvite;
use App\Models\ConversationJoinRequest;
use App\Models\ConversationMember;
use App\Models\LoginHistory;
use App\Models\Message;
use App\Models\MessageEdit;
use App\Models\PinnedMessage;
use App\Models\User;
use App\Models\UserKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ChatController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $conversations = Conversation::forUser($user->id)
            ->with([
                'userA',
                'userB',
                'members.user',
                'latestMessage',
            ])
            ->get()
            ->sortByDesc(fn ($c) => optional($c->latestMessage)->created_at);

        $allFriends = $user->friends->merge($user->friendOf)->unique('id');

        $convPartnerIds = $conversations
            ->reject(fn (Conversation $conversation) => $conversation->isGroup())
            ->map(fn ($c) => $c->otherParticipant($user->id)?->id)
            ->filter()
            ->all();

        $friendsWithoutConv = $allFriends->filter(fn ($f) => ! in_array($f->id, $convPartnerIds))->values();

        $hasPublicKey = $user->userKey()->exists();
        $hasKeyBackup = filled($user->userKey?->key_backup);
        $pendingJoinRequests = ConversationJoinRequest::where('invited_user_id', $user->id)
            ->where('status', ConversationJoinRequest::STATUS_PENDING)
            ->with(['conversation', 'invitedBy'])
            ->latest()
            ->get();

        return view('pages.chat.index', compact('conversations', 'friendsWithoutConv', 'allFriends', 'hasPublicKey', 'hasKeyBackup', 'pendingJoinRequests'));
    }

    public function storePublicKey(Request $request): JsonResponse
    {
        $request->validate([
            'public_key_jwk' => 'required|string|max:2048',
            'key_change_source' => 'nullable|in:fresh,settings',
        ]);

        $existing = UserKey::where('user_id', Auth::id())->first();
        $isNewKey = ! $existing || $existing->public_key_jwk !== $request->public_key_jwk;

        $data = ['public_key_jwk' => $request->public_key_jwk];

        if ($isNewKey && $request->filled('key_change_source')) {
            $data['key_change_source'] = $request->key_change_source;
            $data['key_changed_at'] = now();
        }

        UserKey::updateOrCreate(['user_id' => Auth::id()], $data);

        if ($isNewKey && $request->filled('key_change_source')) {
            LoginHistory::create([
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'event' => 'key_changed',
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function getPublicKey(int $userId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->isFriendWith($userId) && ! $user->sharesGroupWith($userId)) {
            return response()->json(['success' => false, 'message' => 'Нет общего чата'], 403);
        }

        $key = UserKey::where('user_id', $userId)->first();

        if (! $key) {
            return response()->json(['success' => false, 'message' => 'Ключ не найден'], 404);
        }

        return response()->json([
            'success' => true,
            'public_key_jwk' => $key->public_key_jwk,
            'key_warn' => $key->shouldWarnPartners(),
            'key_changed_days_ago' => $key->shouldWarnPartners() ? $key->daysAgoChanged() : null,
        ]);
    }

    public function createGroup(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|min:1|max:60',
            'user_ids' => 'nullable|array|max:50',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $user = Auth::user();
        $participantIds = collect($request->input('user_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === $user->id)
            ->values();

        foreach ($participantIds as $participantId) {
            if (! $user->isFriendWith($participantId)) {
                return response()->json(['success' => false, 'message' => 'Добавлять можно только друзей'], 422);
            }
        }

        $conversation = DB::transaction(function () use ($request, $user, $participantIds) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_GROUP,
                'title' => trim((string) $request->input('title')),
            ]);

            ConversationMember::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => ConversationMember::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            foreach ($participantIds as $participantId) {
                ConversationJoinRequest::create([
                    'conversation_id' => $conversation->id,
                    'invited_user_id' => $participantId,
                    'invited_by_id' => $user->id,
                    'status' => ConversationJoinRequest::STATUS_PENDING,
                ]);
            }

            return $conversation;
        });

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
        ], 201);
    }

    public function participants(int $conversationId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::with(['members.user.userKey'])->findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $pendingInviteUserIds = $conversation->joinRequests()->pluck('invited_user_id');

        return response()->json([
            'success' => true,
            'conversation' => $this->conversationSummary($conversation, $user->id),
            'participants' => $conversation->members
                ->map(fn (ConversationMember $member) => [
                    'id' => $member->user->id,
                    'login' => $member->user->pseudonym,
                    'role' => $member->role,
                    'avatar' => $member->user->avatar ? '/storage/'.$member->user->avatar : null,
                    'public_key_jwk' => $member->user->userKey?->public_key_jwk,
                ])
                ->values(),
            'friends' => $user->friends
                ->merge($user->friendOf)
                ->unique('id')
                ->reject(fn (User $friend) => $conversation->members->contains('user_id', $friend->id))
                ->reject(fn (User $friend) => $pendingInviteUserIds->contains($friend->id))
                ->map(fn (User $friend) => [
                    'id' => $friend->id,
                    'login' => $friend->pseudonym,
                    'avatar' => $friend->avatar ? '/storage/'.$friend->avatar : null,
                ])
                ->values(),
            'invites' => $conversation->invites()
                ->whereNull('revoked_at')
                ->latest()
                ->get()
                ->map(fn (ConversationInvite $invite) => $this->inviteSummary($invite))
                ->values(),
        ]);
    }

    public function addMembers(int $conversationId, Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $user = Auth::user();
        $conversation = Conversation::with('members')->findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->canManageMembers($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $userIds = collect($request->input('user_ids'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === $user->id)
            ->values();

        foreach ($userIds as $userId) {
            if (! $user->isFriendWith($userId)) {
                return response()->json(['success' => false, 'message' => 'Добавлять можно только друзей'], 422);
            }
        }

        foreach ($userIds as $userId) {
            if ($conversation->hasMember($userId)) {
                continue;
            }

            ConversationJoinRequest::firstOrCreate([
                'conversation_id' => $conversation->id,
                'invited_user_id' => $userId,
            ], [
                'invited_by_id' => $user->id,
                'status' => ConversationJoinRequest::STATUS_PENDING,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function updateGroup(int $conversationId, Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|min:1|max:60',
        ]);

        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->canManageMembers($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $conversation->update(['title' => trim((string) $request->input('title'))]);

        return response()->json([
            'success' => true,
            'title' => $conversation->title,
        ]);
    }

    public function updateGroupAvatar(int $conversationId, Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', File::image()->types(['jpeg', 'jpg', 'png', 'gif', 'webp'])->max('5mb')],
        ]);

        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->canManageMembers($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if ($conversation->avatar) {
            Storage::disk('public')->delete($conversation->avatar);
        }

        $filename = 'group_avatars/'.Str::uuid().'.webp';
        $encoded = (new ImageManager(new GdDriver))
            ->decode($request->file('avatar')->getRealPath())
            ->cover(200, 200)
            ->encode(new WebpEncoder(80));
        Storage::disk('public')->put($filename, (string) $encoded);

        $conversation->update(['avatar' => $filename]);

        return response()->json([
            'success' => true,
            'avatar_url' => '/storage/'.$filename,
        ]);
    }

    public function removeMember(int $conversationId, int $userId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::with('members')->findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->canManageMembers($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if ($conversation->isOwner($userId)) {
            return response()->json(['success' => false, 'message' => 'Нельзя удалить владельца'], 422);
        }

        $removedUser = User::findOrFail($userId);
        $member = ConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $member->delete();

        $this->createSystemMessage($conversation, $user, 'member_removed', [
            'actor' => $user->pseudonym,
            'target' => $removedUser->pseudonym,
        ]);

        return response()->json(['success' => true]);
    }

    public function leaveGroup(int $conversationId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::with('members')->findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->hasMember($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        DB::transaction(function () use ($conversation, $user) {
            if ($conversation->isOwner($user->id)) {
                $replacement = $conversation->members
                    ->reject(fn (ConversationMember $member) => $member->user_id === $user->id)
                    ->sortBy(fn (ConversationMember $member) => match ($member->role) {
                        ConversationMember::ROLE_ADMIN => 0,
                        ConversationMember::ROLE_MEMBER => 1,
                        default => 2,
                    })
                    ->first();

                if ($replacement) {
                    $replacement->update(['role' => ConversationMember::ROLE_OWNER]);
                } else {
                    $conversation->delete();

                    return;
                }
            }

            ConversationMember::where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->delete();

            $this->createSystemMessage($conversation, $user, 'member_left', [
                'actor' => $user->pseudonym,
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function acceptJoinRequest(int $joinRequestId): JsonResponse
    {
        $user = Auth::user();
        $joinRequest = ConversationJoinRequest::with('conversation')
            ->where('status', ConversationJoinRequest::STATUS_PENDING)
            ->findOrFail($joinRequestId);

        if ($joinRequest->invited_user_id !== $user->id || ! $joinRequest->conversation->isGroup()) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        DB::transaction(function () use ($joinRequest, $user) {
            ConversationMember::firstOrCreate([
                'conversation_id' => $joinRequest->conversation_id,
                'user_id' => $user->id,
            ], [
                'role' => ConversationMember::ROLE_MEMBER,
                'joined_at' => now(),
            ]);

            $joinRequest->delete();

            $this->createSystemMessage($joinRequest->conversation, $user, 'member_joined', [
                'actor' => $user->pseudonym,
            ]);
        });

        return response()->json([
            'success' => true,
            'conversation_id' => $joinRequest->conversation_id,
        ]);
    }

    public function declineJoinRequest(int $joinRequestId): JsonResponse
    {
        $user = Auth::user();
        $joinRequest = ConversationJoinRequest::where('status', ConversationJoinRequest::STATUS_PENDING)
            ->findOrFail($joinRequestId);

        if ($joinRequest->invited_user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $joinRequest->update(['status' => ConversationJoinRequest::STATUS_DECLINED]);

        return response()->json(['success' => true]);
    }

    public function destroyGroup(int $conversationId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->isOwner($user->id)) {
            return response()->json(['success' => false, 'message' => 'Только владелец может удалить группу'], 403);
        }

        if ($conversation->avatar) {
            Storage::disk('public')->delete($conversation->avatar);
        }

        $conversation->delete();

        return response()->json(['success' => true]);
    }

    public function promoteMember(int $conversationId, int $userId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->isOwner($user->id)) {
            return response()->json(['success' => false, 'message' => 'Только владелец может назначать админов'], 403);
        }

        $member = ConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($member->role !== ConversationMember::ROLE_OWNER) {
            $member->update(['role' => ConversationMember::ROLE_ADMIN]);
        }

        return response()->json(['success' => true]);
    }

    public function demoteMember(int $conversationId, int $userId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->isOwner($user->id)) {
            return response()->json(['success' => false, 'message' => 'Только владелец может менять роли'], 403);
        }

        $member = ConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($member->role === ConversationMember::ROLE_ADMIN) {
            $member->update(['role' => ConversationMember::ROLE_MEMBER]);
        }

        return response()->json(['success' => true]);
    }

    public function createInvite(int $conversationId, Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:permanent,single_use',
        ]);

        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->canManageMembers($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if ($request->input('type') === ConversationInvite::TYPE_PERMANENT) {
            $conversation->invites()
                ->where('type', ConversationInvite::TYPE_PERMANENT)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        $invite = ConversationInvite::create([
            'conversation_id' => $conversation->id,
            'created_by_id' => $user->id,
            'token' => Str::random(48),
            'type' => $request->input('type'),
            'expires_at' => $request->input('type') === ConversationInvite::TYPE_SINGLE_USE ? now()->addDay() : null,
        ]);

        return response()->json([
            'success' => true,
            'invite' => $this->inviteSummary($invite),
        ], 201);
    }

    public function revokeInvite(int $conversationId, int $inviteId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->isGroup() || ! $conversation->canManageMembers($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        ConversationInvite::where('id', $inviteId)
            ->where('conversation_id', $conversation->id)
            ->update(['revoked_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function joinByInvite(string $token): RedirectResponse
    {
        $user = Auth::user();
        $invite = ConversationInvite::where('token', $token)
            ->with('conversation')
            ->firstOrFail();

        if (! $invite->isActive() || ! $invite->conversation->isGroup()) {
            return redirect()->route('chats.index')->with('error', 'Ссылка недействительна');
        }

        $member = ConversationMember::firstOrCreate([
            'conversation_id' => $invite->conversation_id,
            'user_id' => $user->id,
        ], [
            'role' => ConversationMember::ROLE_MEMBER,
            'joined_at' => now(),
        ]);

        if ($member->wasRecentlyCreated) {
            $this->createSystemMessage($invite->conversation, $user, 'member_joined', [
                'actor' => $user->pseudonym,
            ]);
        }

        if ($invite->type === ConversationInvite::TYPE_SINGLE_USE) {
            $invite->update(['used_at' => now()]);
        }

        return redirect()->route('chats.index', ['conversation' => $invite->conversation_id]);
    }

    public function messages(int $conversationId, Request $request): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if (! $conversation->isGroup()) {
            $recipientId = $conversation->user_a_id === $user->id
                ? $conversation->user_b_id
                : $conversation->user_a_id;

            if (! $user->isFriendWith($recipientId)) {
                return response()->json(['success' => false, 'message' => 'Вы больше не друзья'], 403);
            }
        }

        $query = $conversation->messages()->visibleTo($user->id)->orderBy('created_at', 'desc');

        if ($request->filled('before_id')) {
            $query->where('id', '<', (int) $request->before_id);
        }

        $messages = $query->limit(50)->get()->reverse()->values();

        if (! $conversation->isGroup()) {
            $undeliveredIds = $messages
                ->where('sender_id', '!=', $user->id)
                ->whereNull('delivered_at')
                ->pluck('id')
                ->all();

            if (! empty($undeliveredIds)) {
                $deliveredAt = now();
                Message::whereIn('id', $undeliveredIds)->update(['delivered_at' => $deliveredAt]);

                $senderId = $messages->whereIn('id', $undeliveredIds)->first()?->sender_id;

                if ($senderId) {
                    broadcast(new MessageDeliveredEvent(
                        $senderId,
                        $conversationId,
                        $undeliveredIds,
                        $deliveredAt->toIso8601String(),
                    ));
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'sender_id' => $m->sender_id,
                'encrypted_payload' => $m->type === Message::TYPE_SYSTEM ? '' : $this->payloadForUser($m, $user->id),
                'system_payload' => $m->system_payload,
                'delivered_at' => $conversation->isGroup() ? null : $m->delivered_at?->toIso8601String(),
                'read_at' => $conversation->isGroup() ? null : $m->read_at?->toIso8601String(),
                'created_at' => $m->created_at->toIso8601String(),
                'edited_at' => $m->edited_at?->toIso8601String(),
                'reply_to_id' => $m->reply_to_id,
            ]),
            'has_more' => $messages->count() === 50,
        ]);
    }

    public function store(int $conversationId, Request $request): JsonResponse
    {

        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if (! $conversation->isGroup()) {
            $recipientId = $conversation->user_a_id === $user->id
                ? $conversation->user_b_id
                : $conversation->user_a_id;

            if (! $user->isFriendWith($recipientId)) {
                return response()->json(['success' => false, 'message' => 'Вы больше не друзья'], 403);
            }
        }

        $request->validate([
            'encrypted_payload' => $conversation->isGroup() ? 'nullable|string|max:65536' : 'required|string|max:65536',
            'encrypted_payloads' => $conversation->isGroup() ? 'required|array|max:100' : 'nullable|array',
            'encrypted_payloads.*' => 'string|max:65536',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
        ]);

        $encryptedPayload = $conversation->isGroup()
            ? $this->buildGroupEnvelope($conversation, $request->input('encrypted_payloads', []))
            : $request->encrypted_payload;

        if (! $encryptedPayload || ! $this->isValidEncryptedPayload($conversation->isGroup() ? $this->payloadForUserValue($encryptedPayload, $user->id) : $encryptedPayload)) {
            return response()->json(['success' => false, 'message' => 'Неверный формат сообщения'], 422);
        }

        $replyToId = null;
        if ($request->filled('reply_to_id')) {
            $replyExists = Message::where('id', $request->reply_to_id)
                ->where('conversation_id', $conversationId)
                ->whereNull('deleted_at')
                ->exists();
            if ($replyExists) {
                $replyToId = (int) $request->reply_to_id;
            }
        }

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'reply_to_id' => $replyToId,
            'encrypted_payload' => $encryptedPayload,
            'expires_at' => now()->addMonths(3),
        ]);

        if ($conversation->isGroup()) {
            foreach ($conversation->recipientsFor($user->id) as $recipient) {
                $payload = $this->payloadForUser($message, $recipient->id);
                broadcast(new ChatMessageEvent($message, $recipient->id, $user->pseudonym, $payload));
                SendPushNotificationJob::dispatch(
                    $recipient->id,
                    $user->pseudonym,
                    $user->id,
                    $conversationId,
                    $payload,
                    Conversation::TYPE_GROUP,
                    $conversation->title,
                );
            }
        } else {
            broadcast(new ChatMessageEvent($message, $recipientId, $user->pseudonym));
            SendPushNotificationJob::dispatch($recipientId, $user->pseudonym, $user->id, $conversationId, $request->encrypted_payload);
            $this->maybeQueueEmailNotification($recipientId, $user->pseudonym);
        }

        return response()->json([
            'success' => true,
            'id' => $message->id,
            'created_at' => $message->created_at->toIso8601String(),
        ], 201);
    }

    public function update(int $conversationId, int $messageId, Request $request): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $message = Message::where('id', $messageId)
            ->where('conversation_id', $conversationId)
            ->where('sender_id', $user->id)
            ->firstOrFail();

        if ($message->created_at->lt(now()->subHours(48))) {
            return response()->json(['success' => false, 'message' => 'Сообщение слишком старое для редактирования'], 422);
        }

        $request->validate([
            'encrypted_payload' => $conversation->isGroup() ? 'nullable|string|max:65536' : 'required|string|max:65536',
            'encrypted_payloads' => $conversation->isGroup() ? 'required|array|max:100' : 'nullable|array',
            'encrypted_payloads.*' => 'string|max:65536',
        ]);

        $encryptedPayload = $conversation->isGroup()
            ? $this->buildGroupEnvelope($conversation, $request->input('encrypted_payloads', []))
            : $request->encrypted_payload;

        if (! $encryptedPayload || ! $this->isValidEncryptedPayload($conversation->isGroup() ? $this->payloadForUserValue($encryptedPayload, $user->id) : $encryptedPayload)) {
            return response()->json(['success' => false, 'message' => 'Неверный формат сообщения'], 422);
        }

        MessageEdit::create([
            'message_id' => $message->id,
            'encrypted_payload' => $message->encrypted_payload,
        ]);

        $editedAt = now();
        $message->update([
            'encrypted_payload' => $encryptedPayload,
            'edited_at' => $editedAt,
        ]);

        if ($conversation->isGroup()) {
            foreach ($conversation->recipientsFor($user->id) as $recipient) {
                $payload = $this->payloadForUser($message, $recipient->id);
                broadcast(new MessageEditedEvent($message, $recipient->id, $payload));
            }
        } else {
            $recipientId = $conversation->user_a_id === $user->id
                ? $conversation->user_b_id
                : $conversation->user_a_id;

            broadcast(new MessageEditedEvent($message, $recipientId));
        }

        return response()->json([
            'success' => true,
            'edited_at' => $editedAt->toIso8601String(),
        ]);
    }

    public function destroy(int $conversationId, int $messageId, Request $request): JsonResponse
    {
        $request->validate([
            'scope' => 'required|in:all,me',
        ]);

        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $message = Message::where('id', $messageId)
            ->where('conversation_id', $conversationId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        if ($request->scope === 'all') {
            $message->update(['deleted_at' => now()]);
            foreach ($conversation->recipientsFor($user->id) as $recipient) {
                broadcast(new MessageDeletedEvent($messageId, $conversationId, $recipient->id));
            }
        } else {
            $deletedFor = $message->deleted_for ?? [];
            if (! in_array($user->id, $deletedFor, strict: true)) {
                $deletedFor[] = $user->id;
                $message->update(['deleted_for' => $deletedFor]);
            }
        }

        return response()->json(['success' => true]);
    }

    public function messageEdits(int $conversationId, int $messageId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $message = Message::where('id', $messageId)
            ->where('conversation_id', $conversationId)
            ->firstOrFail();

        $edits = $message->edits()->get()->map(fn ($e) => [
            'encrypted_payload' => $conversation->isGroup() ? $this->payloadForUserValue($e->encrypted_payload, $user->id) : $e->encrypted_payload,
            'created_at' => $e->created_at->toIso8601String(),
        ]);

        return response()->json(['success' => true, 'data' => $edits]);
    }

    public function markDelivered(Request $request): JsonResponse
    {
        return $this->markMessageStatus($request, 'delivered_at', MessageDeliveredEvent::class);
    }

    public function markRead(Request $request): JsonResponse
    {
        return $this->markMessageStatus($request, 'read_at', MessageReadEvent::class);
    }

    public function typing(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $conversation = Conversation::findOrFail($request->conversation_id);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if (! $conversation->isGroup()) {
            $recipientId = $conversation->user_a_id === $user->id
                ? $conversation->user_b_id
                : $conversation->user_a_id;

            if (! $user->isFriendWith($recipientId)) {
                return response()->json(['success' => false, 'message' => 'Вы больше не друзья'], 403);
            }

            broadcast(new TypingEvent($user->id, $recipientId, $request->conversation_id));
        } else {
            foreach ($conversation->recipientsFor($user->id) as $recipient) {
                broadcast(new TypingEvent($user->id, $recipient->id, $request->conversation_id));
            }
        }

        return response()->json(['success' => true]);
    }

    public function getSettings(): JsonResponse
    {
        $key = Auth::user()->userKey;

        return response()->json([
            'success' => true,
            'storage_preference' => $key?->storage_preference ?? 'server',
        ]);
    }

    public function storeSettings(Request $request): JsonResponse
    {
        $request->validate([
            'storage_preference' => 'required|in:server,browser,device',
        ]);

        UserKey::updateOrCreate(
            ['user_id' => Auth::id()],
            ['storage_preference' => $request->storage_preference],
        );

        return response()->json(['success' => true]);
    }

    public function storeKeyBackup(Request $request): JsonResponse
    {
        $request->validate([
            'key_backup' => 'required|string|max:4096',
        ]);

        $payload = json_decode($request->key_backup, true);
        if (
            ! is_array($payload)
            || ! isset($payload['salt'], $payload['iv'], $payload['ciphertext'])
            || ! is_string($payload['salt'])
            || ! is_string($payload['iv'])
            || ! is_string($payload['ciphertext'])
        ) {
            return response()->json(['success' => false, 'message' => 'Неверный формат бэкапа'], 422);
        }

        UserKey::updateOrCreate(
            ['user_id' => Auth::id()],
            ['key_backup' => $request->key_backup],
        );

        return response()->json(['success' => true]);
    }

    public function getKeyBackup(): JsonResponse
    {
        $key = Auth::user()->userKey;

        return response()->json([
            'success' => true,
            'key_backup' => $key?->key_backup,
        ]);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = Auth::user();
        $partnerId = (int) $request->user_id;

        if (! $user->isFriendWith($partnerId)) {
            return response()->json(['success' => false, 'message' => 'Не является другом'], 403);
        }

        $conversation = Conversation::findOrCreateBetween($user->id, $partnerId);

        return response()->json(['success' => true, 'conversation_id' => $conversation->id]);
    }

    public function pins(int $conversationId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $pins = PinnedMessage::where('conversation_id', $conversationId)
            ->with('message')
            ->orderBy('created_at', 'asc')
            ->get()
            ->filter(fn ($p) => $p->message && ! $p->message->deleted_at)
            ->map(fn ($p) => [
                'message_id' => $p->message_id,
                'encrypted_payload' => $conversation->isGroup() ? $this->payloadForUser($p->message, $user->id) : $p->message->encrypted_payload,
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $pins]);
    }

    public function pinMessage(int $conversationId, int $messageId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $message = Message::where('id', $messageId)
            ->where('conversation_id', $conversationId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        PinnedMessage::firstOrCreate([
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
        ], [
            'pinned_by_id' => $user->id,
        ]);

        foreach ($conversation->recipientsFor($user->id) as $recipient) {
            $payload = $conversation->isGroup() ? $this->payloadForUser($message, $recipient->id) : $message->encrypted_payload;
            broadcast(new MessagePinnedEvent($messageId, $conversationId, $payload, true, $recipient->id));
        }

        return response()->json(['success' => true]);
    }

    public function unpinMessage(int $conversationId, int $messageId): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $message = Message::where('id', $messageId)
            ->where('conversation_id', $conversationId)
            ->firstOrFail();

        PinnedMessage::where('conversation_id', $conversationId)
            ->where('message_id', $messageId)
            ->delete();

        foreach ($conversation->recipientsFor($user->id) as $recipient) {
            $payload = $conversation->isGroup() ? $this->payloadForUser($message, $recipient->id) : $message->encrypted_payload;
            broadcast(new MessagePinnedEvent($messageId, $conversationId, $payload, false, $recipient->id));
        }

        return response()->json(['success' => true]);
    }

    private function markMessageStatus(Request $request, string $column, string $eventClass): JsonResponse
    {
        $request->validate([
            'message_ids' => 'required|array|max:100',
            'message_ids.*' => 'integer|min:1',
            'conversation_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $conversation = Conversation::findOrFail($request->conversation_id);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if ($conversation->isGroup()) {
            return response()->json(['success' => true]);
        }

        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        if (! $user->isFriendWith($recipientId)) {
            return response()->json(['success' => false, 'message' => 'Вы больше не друзья'], 403);
        }

        $timestamp = now();
        $updated = Message::whereIn('id', $request->message_ids)
            ->where('conversation_id', $request->conversation_id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull($column)
            ->get();

        if ($updated->isNotEmpty()) {
            Message::whereIn('id', $updated->pluck('id'))->update([$column => $timestamp]);

            broadcast(new $eventClass(
                $updated->first()->sender_id,
                $request->conversation_id,
                $updated->pluck('id')->all(),
                $timestamp->toIso8601String(),
            ));
        }

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationSummary(Conversation $conversation, int $userId): array
    {
        if ($conversation->isGroup()) {
            return [
                'id' => $conversation->id,
                'type' => Conversation::TYPE_GROUP,
                'title' => $conversation->title,
                'avatar_url' => $conversation->avatar ? '/storage/'.$conversation->avatar : null,
                'role' => $conversation->roleFor($userId),
                'can_manage' => $conversation->canManageMembers($userId),
                'is_owner' => $conversation->isOwner($userId),
            ];
        }

        $partner = $conversation->otherParticipant($userId);

        return [
            'id' => $conversation->id,
            'type' => Conversation::TYPE_DIRECT,
            'title' => $partner->pseudonym,
            'partner_id' => $partner->id,
            'can_manage' => false,
            'is_owner' => false,
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function createSystemMessage(Conversation $conversation, User $actor, string $event, array $payload): Message
    {
        $message = Message::create([
            'type' => Message::TYPE_SYSTEM,
            'conversation_id' => $conversation->id,
            'sender_id' => $actor->id,
            'encrypted_payload' => '',
            'system_payload' => array_merge(['event' => $event], $payload),
            'expires_at' => now()->addMonths(3),
        ]);

        foreach ($conversation->participants()->get() as $recipient) {
            broadcast(new ChatMessageEvent($message, $recipient->id, $actor->pseudonym, ''));
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function inviteSummary(ConversationInvite $invite): array
    {
        return [
            'id' => $invite->id,
            'type' => $invite->type,
            'url' => route('chat.invites.join', ['token' => $invite->token]),
            'expires_at' => $invite->expires_at?->toIso8601String(),
            'used_at' => $invite->used_at?->toIso8601String(),
        ];
    }

    private function payloadForUser(Message $message, int $userId): string
    {
        return $this->payloadForUserValue($message->encrypted_payload, $userId);
    }

    private function payloadForUserValue(string $encryptedPayload, int $userId): string
    {
        $payload = json_decode($encryptedPayload, true);

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'group') {
            return $encryptedPayload;
        }

        $userPayload = $payload['payloads'][(string) $userId] ?? null;

        return is_string($userPayload) ? $userPayload : '';
    }

    /**
     * @param  array<string, string>  $payloads
     */
    private function buildGroupEnvelope(Conversation $conversation, array $payloads): ?string
    {
        $memberIds = $conversation->participants()->pluck('users.id')->map(fn ($id) => (string) $id);

        foreach ($memberIds as $memberId) {
            $payload = $payloads[$memberId] ?? $payloads[(int) $memberId] ?? null;

            if (! is_string($payload) || ! $this->isValidEncryptedPayload($payload)) {
                return null;
            }
        }

        $filteredPayloads = [];
        foreach ($memberIds as $memberId) {
            $filteredPayloads[$memberId] = $payloads[$memberId] ?? $payloads[(int) $memberId];
        }

        $envelope = json_encode([
            'type' => 'group',
            'payloads' => $filteredPayloads,
        ]);

        return is_string($envelope) ? $envelope : null;
    }

    private function isValidEncryptedPayload(?string $encryptedPayload): bool
    {
        if (! is_string($encryptedPayload) || $encryptedPayload === '') {
            return false;
        }

        $payload = json_decode($encryptedPayload, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['ciphertext'])
            && is_string($payload['iv'])
            && is_string($payload['ciphertext'])
            && base64_decode($payload['iv'], true) !== false
            && base64_decode($payload['ciphertext'], true) !== false;
    }

    private function maybeQueueEmailNotification(int $recipientId, string $senderLogin): void
    {
        $recipient = User::find($recipientId);

        if (! $recipient?->email || ! $recipient->email_verified_at) {
            return;
        }

        $userKey = $recipient->userKey;

        if (! $userKey?->notify_email) {
            return;
        }

        // Send only if user is currently offline (no heartbeat in the last minute)
        $isOnline = $recipient->last_seen_at?->gt(now()->subMinute());
        if ($isOnline) {
            return;
        }

        SendNewMessageEmailJob::dispatch($recipientId, $senderLogin);
    }
}
