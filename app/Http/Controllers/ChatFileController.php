<?php

namespace App\Http\Controllers;

use App\Models\ChatFile;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatFileController extends Controller
{
    private const MAX_CHUNK_SIZE = 1_600_000; // 1.5MB + encryption overhead (PHP upload_max_filesize = 2M)

    private const MAX_TOTAL_SIZE = 550_000_000; // 500MB + encryption overhead

    private const EXPIRY_DAYS = 2;

    public function uploadChunk(int $conversationId, Request $request): JsonResponse
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
            'upload_uuid' => 'required|uuid',
            'chunk_index' => 'required|integer|min:0|max:9999',
            'total_chunks' => 'required|integer|min:1|max:10000',
            'chunk' => 'required|file|max:'.(int) (self::MAX_CHUNK_SIZE / 1024),
        ]);

        $uploadUuid = $request->input('upload_uuid');
        $chunkIndex = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');
        $chunkFile = $request->file('chunk');

        $chunkDir = 'chat-uploads/'.$uploadUuid;

        // Guard total upload size across all chunks
        $existingChunks = Storage::disk('local')->files($chunkDir);
        $totalSoFar = array_reduce($existingChunks, fn ($carry, $path) => $carry + Storage::disk('local')->size($path), 0);
        $totalSoFar += $chunkFile->getSize();

        if ($totalSoFar > self::MAX_TOTAL_SIZE) {
            Storage::disk('local')->deleteDirectory($chunkDir);

            return response()->json(['success' => false, 'message' => 'Файл слишком большой'], 422);
        }

        // Store raw encrypted binary — never execute on server
        $chunkPath = $chunkDir.'/chunk_'.str_pad((string) $chunkIndex, 5, '0', STR_PAD_LEFT);
        Storage::disk('local')->put($chunkPath, $chunkFile->get());

        $storedChunks = count(Storage::disk('local')->files($chunkDir));

        if ($storedChunks < $totalChunks) {
            return response()->json([
                'success' => true,
                'status' => 'partial',
                'chunks_received' => $storedChunks,
            ]);
        }

        // Assemble all chunks into final file
        $fileUuid = Str::uuid()->toString();
        $finalPath = 'chat-files/'.$fileUuid;

        $assembled = '';
        for ($i = 0; $i < $totalChunks; $i++) {
            $part = $chunkDir.'/chunk_'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);

            if (! Storage::disk('local')->exists($part)) {
                Storage::disk('local')->deleteDirectory($chunkDir);

                return response()->json(['success' => false, 'message' => 'Отсутствует часть файла'], 422);
            }

            $assembled .= Storage::disk('local')->get($part);
        }

        Storage::disk('local')->put($finalPath, $assembled);
        Storage::disk('local')->deleteDirectory($chunkDir);

        $chatFile = ChatFile::create([
            'uuid' => $fileUuid,
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'size_encrypted' => Storage::disk('local')->size($finalPath),
            'storage_path' => $finalPath,
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
        ]);

        return response()->json([
            'success' => true,
            'status' => 'complete',
            'file_uuid' => $chatFile->uuid,
            'expires_at' => $chatFile->expires_at->toIso8601String(),
        ], 201);
    }

    public function download(string $fileUuid): Response|JsonResponse
    {
        $user = Auth::user();
        $chatFile = ChatFile::where('uuid', $fileUuid)->with('conversation')->firstOrFail();

        if (! $chatFile->conversation->hasParticipant($user->id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        if (! $chatFile->conversation->isGroup()) {
            $recipientId = $chatFile->conversation->user_a_id === $user->id
                ? $chatFile->conversation->user_b_id
                : $chatFile->conversation->user_a_id;

            if (! $user->isFriendWith($recipientId)) {
                return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
            }
        }

        if ($chatFile->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Файл истёк'], 410);
        }

        if (! Storage::disk('local')->exists($chatFile->storage_path)) {
            return response()->json(['success' => false, 'message' => 'Файл не найден'], 404);
        }

        $content = Storage::disk('local')->get($chatFile->storage_path);

        return response($content, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$fileUuid.'.bin"',
            'Content-Length' => strlen($content),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
