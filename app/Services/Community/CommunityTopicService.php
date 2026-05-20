<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\CommunityTopic;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CommunityTopicService
{
    public function __construct(
        private readonly CommunityPolicyService $policy,
        private readonly CommunityAuditService $audit,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     slug?: string|null,
     *     description?: string|null,
     *     posting_policy?: string|null,
     * } $data
     */
    public function createTopic(User $actor, Community $community, array $data): CommunityTopic
    {
        if (! $this->policy->canManageTopic($actor, $community)) {
            throw new InvalidArgumentException('Actor is not allowed to create topics in this community.');
        }

        $slug = $data['slug'] ?? Str::slug($data['name']);

        $slugTaken = CommunityTopic::where('community_id', $community->id)
            ->where('slug', $slug)
            ->exists();

        if ($slugTaken) {
            throw new InvalidArgumentException("Topic slug '{$slug}' is already taken in this community.");
        }

        $topic = CommunityTopic::create([
            'community_id' => $community->id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'type' => CommunityTopic::TYPE_REGULAR,
            'posting_policy' => $data['posting_policy'] ?? null,
            'sort_order' => 0,
            'post_count' => 0,
            'created_by' => $actor->id,
            'is_system' => false,
            'is_pinned' => false,
            'is_archived' => false,
        ]);

        $this->audit->log($community, $actor, CommunityAuditLog::ACTION_TOPIC_CREATED, [
            'topic_id' => $topic->id,
            'name' => $data['name'],
        ]);

        return $topic;
    }

    public function archiveTopic(User $actor, CommunityTopic $topic): void
    {
        $community = $topic->community;

        if (! $this->policy->canManageTopic($actor, $community)) {
            throw new InvalidArgumentException('Actor is not allowed to archive topics in this community.');
        }

        if ($topic->is_system) {
            throw new InvalidArgumentException('System topics cannot be archived.');
        }

        if ($topic->is_archived) {
            throw new InvalidArgumentException('Topic is already archived.');
        }

        $topic->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $this->audit->log($community, $actor, CommunityAuditLog::ACTION_TOPIC_ARCHIVED, [
            'topic_id' => $topic->id,
        ]);
    }
}
