<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\User;

final class CommunityAuditService
{
    /** Keys that must never be stored in the audit payload. */
    private const BLOCKED_KEYS = [
        'ciphertext',
        'nonce',
        'encrypted_key',
        'code',
        'encrypted_ban_note',
        'path',
        'storage_key',
        'public_key',
        'fingerprint',
        'body',
        'encrypted_filename',
    ];

    public function log(
        Community $community,
        User $actor,
        string $action,
        ?array $payload = null,
        ?User $targetUser = null,
    ): void {
        CommunityAuditLog::create([
            'community_id' => $community->id,
            'actor_id' => $actor->id,
            'target_user_id' => $targetUser?->id,
            'action' => $action,
            'payload' => $this->sanitizePayload($payload),
        ]);
    }

    /** @param  array<string, mixed>|null  $payload */
    private function sanitizePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return array_diff_key($payload, array_flip(self::BLOCKED_KEYS));
    }
}
