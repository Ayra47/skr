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
     *     ciphertext: string,
     *     nonce: string,
     *     epoch_id: string,
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

        return DB::transaction(function () use ($author, $community, $topic, $payload, $ttlSeconds, $idempotencyKey): CommunityPost {
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

            $epoch = CommunityKeyEpoch::where('id', $payload['epoch_id'])
                ->where('community_id', $community->id)
                ->lockForUpdate()
                ->firstOrFail();

            $communitySeq = $community->lockForUpdate()->value('post_count') + 1;
            $topicSeq = $topic->lockForUpdate()->value('post_count') + 1;

            $expiresAt = $ttlSeconds !== null ? now()->addSeconds($ttlSeconds) : null;

            $post = CommunityPost::create([
                'community_id' => $community->id,
                'topic_id' => $topic->id,
                'user_id' => $author->id,
                'epoch_id' => $epoch->id,
                'ciphertext' => $payload['ciphertext'],
                'nonce' => $payload['nonce'],
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
}
