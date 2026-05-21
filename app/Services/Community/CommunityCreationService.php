<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityMember;
use App\Models\CommunityTopic;
use App\Models\CommunityUserState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CommunityCreationService
{
    public function __construct(
        private readonly CommunityAuditService $audit,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     slug?: string,
     *     description?: string|null,
     *     join_mode?: string,
     *     visibility?: string,
     *     member_limit?: int|null,
     *     default_post_ttl_seconds?: int|null,
     *     invite_policy?: string,
     *     posting_policy?: string,
     *     allow_posts_in_member_feed?: bool,
     *     hide_real_names?: bool,
     *     show_key_fingerprints?: bool,
     *     anonymous_reactions_enabled?: bool,
     * } $data
     */
    public function create(User $creator, array $data): Community
    {
        return DB::transaction(function () use ($creator, $data): Community {
            $community = Community::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::random(6),
                'description' => $data['description'] ?? null,
                'created_by' => $creator->id,
                'join_mode' => $data['join_mode'] ?? Community::JOIN_OPEN,
                'visibility' => $data['visibility'] ?? Community::VISIBILITY_PUBLIC,
                'member_count' => 1,
                'post_count' => 0,
                'member_limit' => $data['member_limit'] ?? null,
                'default_post_ttl_seconds' => $data['default_post_ttl_seconds'] ?? null,
                'invite_policy' => $data['invite_policy'] ?? Community::INVITE_POLICY_ALL_MEMBERS,
                'posting_policy' => $data['posting_policy'] ?? Community::POSTING_POLICY_EVERYONE,
                'allow_posts_in_member_feed' => $data['allow_posts_in_member_feed'] ?? true,
                'hide_real_names' => $data['hide_real_names'] ?? false,
                'show_key_fingerprints' => $data['show_key_fingerprints'] ?? true,
                'anonymous_reactions_enabled' => $data['anonymous_reactions_enabled'] ?? false,
            ]);

            CommunityMember::create([
                'community_id' => $community->id,
                'user_id' => $creator->id,
                'role' => CommunityMember::ROLE_OWNER,
                'status' => CommunityMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            $topicName = 'general';
            CommunityTopic::create([
                'community_id' => $community->id,
                'name' => $topicName,
                'slug' => 'general',
                'type' => CommunityTopic::TYPE_REGULAR,
                'sort_order' => 0,
                'post_count' => 0,
                'created_by' => $creator->id,
                'is_system' => true,
                'is_pinned' => false,
                'is_archived' => false,
            ]);

            CommunityUserState::create([
                'community_id' => $community->id,
                'user_id' => $creator->id,
                'notifications_enabled' => true,
                'muted' => false,
                'unread_posts_count' => 0,
                'pinned' => false,
                'last_read_community_seq' => 0,
            ]);

            CommunityKeyEpoch::create([
                'community_id' => $community->id,
                'epoch_number' => 1,
                'reason' => CommunityKeyEpoch::REASON_INITIAL,
            ]);

            $this->audit->log($community, $creator, CommunityAuditLog::ACTION_COMMUNITY_CREATED);

            return $community;
        });
    }
}
