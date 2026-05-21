<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CommunityPostService
{
    public function __construct(
        private readonly CommunityPolicyService $policy,
    ) {}

    /**
     * @param  array{
     *     body?: string|null,
     *     ciphertext?: string|null,
     *     nonce?: string|null,
     *     epoch_id?: string|null,
     *     visibility?: string,
     *     ttl_seconds?: int|null,
     *     client_idempotency_key?: string|null,
     * } $payload
     */
    public function publishPost(
        User $author,
        Community $community,
        CommunityTopic $topic,
        array $payload,
    ): CommunityPost {
        if (! $this->policy->canPostInTopic($author, $topic)) {
            throw new InvalidArgumentException('User is not allowed to post in this topic.');
        }

        if ($topic->community_id !== $community->id) {
            throw new InvalidArgumentException('Topic does not belong to the given community.');
        }

        $explicitTtl = array_key_exists('ttl_seconds', $payload) ? $payload['ttl_seconds'] : -1;
        $ttlSeconds = $explicitTtl === -1
            ? $community->default_post_ttl_seconds
            : $explicitTtl;

        if ($ttlSeconds !== null && $ttlSeconds <= 0) {
            throw new InvalidArgumentException('ttl_seconds must be positive.');
        }

        $idempotencyKey = $payload['client_idempotency_key'] ?? null;
        $body = filled($payload['body'] ?? null) ? trim((string) $payload['body']) : null;
        $isPlaintext = filled($body);
        $isEncrypted = filled($payload['ciphertext'] ?? null) && filled($payload['nonce'] ?? null);

        if (! $isPlaintext && ! $isEncrypted) {
            throw new InvalidArgumentException('Community post must include body or encrypted payload.');
        }

        // Fast-path deduplication before acquiring any locks.
        if ($idempotencyKey !== null) {
            $existing = CommunityPost::where('community_id', $community->id)
                ->where('user_id', $author->id)
                ->where('client_idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($author, $community, $topic, $payload, $ttlSeconds, $idempotencyKey, $body, $isPlaintext): CommunityPost {
            // Re-check idempotency inside the transaction under lock to close the race window.
            if ($idempotencyKey !== null) {
                $existing = CommunityPost::where('community_id', $community->id)
                    ->where('user_id', $author->id)
                    ->where('client_idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $epochId = null;

            if (filled($payload['epoch_id'] ?? null)) {
                $epochId = CommunityKeyEpoch::where('id', $payload['epoch_id'])
                    ->where('community_id', $community->id)
                    ->lockForUpdate()
                    ->value('id');

                if ($epochId === null) {
                    throw new InvalidArgumentException('Key epoch does not belong to this community.');
                }
            }

            $communitySeq = $community->lockForUpdate()->value('post_count') + 1;
            $topicSeq = $topic->lockForUpdate()->value('post_count') + 1;

            $expiresAt = $ttlSeconds !== null ? now()->addSeconds($ttlSeconds) : null;

            $post = CommunityPost::create([
                'community_id' => $community->id,
                'topic_id' => $topic->id,
                'user_id' => $author->id,
                'epoch_id' => $epochId,
                'body' => $isPlaintext ? $body : null,
                'ciphertext' => $isPlaintext ? null : ($payload['ciphertext'] ?? null),
                'nonce' => $isPlaintext ? null : ($payload['nonce'] ?? null),
                'community_seq' => $communitySeq,
                'topic_seq' => $topicSeq,
                'visibility' => $payload['visibility'] ?? CommunityPost::VISIBILITY_MEMBERS_ONLY,
                'is_pinned' => false,
                'reaction_count' => 0,
                'comment_count' => 0,
                'reply_count' => 0,
                'attachments_count' => 0,
                'moderation_status' => CommunityPost::MODERATION_VISIBLE,
                'ttl_seconds' => $ttlSeconds,
                'expires_at' => $expiresAt,
                'client_idempotency_key' => $idempotencyKey,
            ]);

            $community->increment('post_count');
            $topic->increment('post_count');

            return $post;
        });
    }

    /**
     * @param  array{
     *     body?: string|null,
     *     ciphertext?: string|null,
     *     nonce?: string|null,
     *     epoch_id?: string|null,
     *     visibility?: string,
     *     ttl_seconds?: int|null,
     *     client_idempotency_key?: string|null,
     * } $payload
     */
    public function publishEncryptedPost(
        User $author,
        Community $community,
        CommunityTopic $topic,
        array $payload,
    ): CommunityPost {
        return $this->publishPost($author, $community, $topic, $payload);
    }
}
