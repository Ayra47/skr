<?php

namespace App\Http\Controllers;

use App\Events\LocationUpdateEvent;
use App\Models\Conversation;
use App\Models\LocationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LocationController extends Controller
{
    /**
     * Create a live location session (called before sending the chat message).
     */
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
            'duration_minutes' => 'required|integer|in:5,15,30,60,180',
        ]);

        $durationMinutes = (int) $request->input('duration_minutes');

        $session = LocationSession::create([
            'uuid' => Str::uuid()->toString(),
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'duration_minutes' => $durationMinutes,
            'expires_at' => now()->addMinutes($durationMinutes),
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $session->uuid,
            'expires_at' => $session->expires_at->toIso8601String(),
        ], 201);
    }

    /**
     * Receive an encrypted position update from the sender and relay to recipient.
     */
    public function updatePosition(int $conversationId, string $sessionUuid, Request $request): JsonResponse
    {
        $user = Auth::user();
        $session = LocationSession::where('uuid', $sessionUuid)->firstOrFail();

        if ($session->sender_id !== $user->id || $session->conversation_id !== $conversationId) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if (! $session->isActive()) {
            return response()->json(['success' => false, 'message' => 'Сессия завершена'], 410);
        }

        $request->validate([
            'encrypted_payload' => 'required|string|max:2048',
        ]);

        $encryptedPayload = $request->input('encrypted_payload');
        $session->update(['last_encrypted_payload' => $encryptedPayload]);

        $conversation = $session->conversation;
        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        broadcast(new LocationUpdateEvent(
            recipientId: $recipientId,
            sessionUuid: $sessionUuid,
            conversationId: $conversationId,
            encryptedPayload: $encryptedPayload,
        ));

        return response()->json(['success' => true]);
    }

    /**
     * Stop live sharing early.
     */
    public function stop(int $conversationId, string $sessionUuid, Request $request): JsonResponse
    {
        $user = Auth::user();
        $session = LocationSession::where('uuid', $sessionUuid)->firstOrFail();

        if ($session->sender_id !== $user->id || $session->conversation_id !== $conversationId) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if ($session->stopped_at !== null) {
            return response()->json(['success' => true]);
        }

        $session->update(['stopped_at' => now()]);

        $conversation = $session->conversation;
        $recipientId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        // Relay stop event with last known position so recipient map freezes correctly
        if ($session->last_encrypted_payload) {
            broadcast(new LocationUpdateEvent(
                recipientId: $recipientId,
                sessionUuid: $sessionUuid,
                conversationId: $conversationId,
                encryptedPayload: $session->last_encrypted_payload,
                stopped: true,
            ));
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get last encrypted position for offline catch-up.
     */
    public function show(int $conversationId, string $sessionUuid): JsonResponse
    {
        $user = Auth::user();
        $session = LocationSession::where('uuid', $sessionUuid)->firstOrFail();

        $conversation = Conversation::findOrFail($conversationId);
        if (! $conversation->hasParticipant($user->id) || $session->conversation_id !== $conversationId) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        return response()->json([
            'success' => true,
            'encrypted_payload' => $session->last_encrypted_payload,
            'is_active' => $session->isActive(),
            'expires_at' => $session->expires_at?->toIso8601String(),
            'stopped_at' => $session->stopped_at?->toIso8601String(),
        ]);
    }
}
