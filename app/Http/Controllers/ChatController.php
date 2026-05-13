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
use App\Models\LoginHistory;
use App\Models\Message;
use App\Models\MessageEdit;
use App\Models\PinnedMessage;
use App\Models\User;
use App\Models\UserKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $conversations = Conversation::forUser($user->id)
            ->with([
                'userA',
                'userB',
                'latestMessage',
            ])
            ->get()
            ->sortByDesc(fn ($c) => optional($c->latestMessage)->created_at);

        $allFriends = $user->friends->merge($user->friendOf)->unique('id');

        $convPartnerIds = $conversations
            ->map(fn ($c) => $c->otherParticipant($user->id)?->id)
            ->filter()
            ->all();

        $friendsWithoutConv = $allFriends->filter(fn ($f) => ! in_array($f->id, $convPartnerIds))->values();

        $hasPublicKey = $user->userKey()->exists();
        $hasKeyBackup = filled($user->userKey?->key_backup);

        return view('pages.chat.index', compact('conversations', 'friendsWithoutConv', 'hasPublicKey', 'hasKeyBackup'));
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

        if (! $user->isFriendWith($userId)) {
            return response()->json(['success' => false, 'message' => 'Не является другом'], 403);
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

    public function messages(int $conversationId, Request $request): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        if (! $user->isFriendWith($recipientId)) {
            return response()->json(['success' => false, 'message' => 'Вы больше не друзья'], 403);
        }

        $query = $conversation->messages()->visibleTo($user->id)->orderBy('created_at', 'desc');

        if ($request->filled('before_id')) {
            $query->where('id', '<', (int) $request->before_id);
        }

        $messages = $query->limit(50)->get()->reverse()->values();

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

        return response()->json([
            'success' => true,
            'data' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'sender_id' => $m->sender_id,
                'encrypted_payload' => $m->encrypted_payload,
                'delivered_at' => $m->delivered_at?->toIso8601String(),
                'read_at' => $m->read_at?->toIso8601String(),
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

        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        if (! $user->isFriendWith($recipientId)) {
            return response()->json(['success' => false, 'message' => 'Вы больше не друзья'], 403);
        }

        $request->validate([
            'encrypted_payload' => 'required|string|max:65536',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
        ]);

        $payload = json_decode($request->encrypted_payload, true);
        if (
            ! is_array($payload)
            || ! isset($payload['iv'], $payload['ciphertext'])
            || ! is_string($payload['iv'])
            || ! is_string($payload['ciphertext'])
            || base64_decode($payload['iv'], true) === false
            || base64_decode($payload['ciphertext'], true) === false
        ) {
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
            'encrypted_payload' => $request->encrypted_payload,
            'expires_at' => now()->addMonths(3),
        ]);

        broadcast(new ChatMessageEvent($message, $recipientId, $user->pseudonym));

        SendPushNotificationJob::dispatch($recipientId, $user->pseudonym, $user->id, $conversationId, $request->encrypted_payload);

        $this->maybeQueueEmailNotification($recipientId, $user->pseudonym);

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
            'encrypted_payload' => 'required|string|max:65536',
        ]);

        $payload = json_decode($request->encrypted_payload, true);
        if (
            ! is_array($payload)
            || ! isset($payload['iv'], $payload['ciphertext'])
            || ! is_string($payload['iv'])
            || ! is_string($payload['ciphertext'])
            || base64_decode($payload['iv'], true) === false
            || base64_decode($payload['ciphertext'], true) === false
        ) {
            return response()->json(['success' => false, 'message' => 'Неверный формат сообщения'], 422);
        }

        MessageEdit::create([
            'message_id' => $message->id,
            'encrypted_payload' => $message->encrypted_payload,
        ]);

        $editedAt = now();
        $message->update([
            'encrypted_payload' => $request->encrypted_payload,
            'edited_at' => $editedAt,
        ]);

        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        broadcast(new MessageEditedEvent($message, $recipientId));

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

        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        if ($request->scope === 'all') {
            $message->update(['deleted_at' => now()]);
            broadcast(new MessageDeletedEvent($messageId, $conversationId, $recipientId));
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
            'encrypted_payload' => $e->encrypted_payload,
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

        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        if (! $user->isFriendWith($recipientId)) {
            return response()->json(['success' => false, 'message' => 'Вы больше не друзья'], 403);
        }

        broadcast(new TypingEvent($user->id, $recipientId, $request->conversation_id));

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
                'encrypted_payload' => $p->message->encrypted_payload,
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

        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        broadcast(new MessagePinnedEvent($messageId, $conversationId, $message->encrypted_payload, true, $recipientId));

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

        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        broadcast(new MessagePinnedEvent($messageId, $conversationId, $message->encrypted_payload, false, $recipientId));

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
