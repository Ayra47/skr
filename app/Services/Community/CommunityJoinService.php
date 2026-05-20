<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\CommunityInvite;
use App\Models\CommunityInviteUse;
use App\Models\CommunityJoinRequest;
use App\Models\CommunityMember;
use App\Models\CommunityUserState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class CommunityJoinService
{
    public function __construct(
        private readonly CommunityPolicyService $policy,
        private readonly CommunityAuditService $audit,
    ) {}

    public function joinPublic(User $user, Community $community): CommunityMember
    {
        if ($community->join_mode !== Community::JOIN_OPEN) {
            throw new InvalidArgumentException('Community is not open for direct joining.');
        }

        return DB::transaction(fn (): CommunityMember => $this->insertMember($user, $community));
    }

    public function joinByInvite(User $user, string $code): CommunityMember
    {
        $invite = CommunityInvite::where('code', $code)->first();

        if ($invite === null || ! $invite->isUsable()) {
            throw new InvalidArgumentException('Invite code is invalid or expired.');
        }

        $communityId = $invite->community_id;

        return DB::transaction(function () use ($user, $code, $communityId): CommunityMember {
            // Re-fetch and lock the invite row to close the race window on concurrent use.
            $invite = CommunityInvite::where('code', $code)->lockForUpdate()->firstOrFail();

            if (! $invite->isUsable()) {
                throw new RuntimeException('Invite code is no longer valid.');
            }

            $community = Community::where('id', $communityId)->lockForUpdate()->firstOrFail();

            $member = $this->insertMember($user, $community);

            $invite->increment('use_count');

            CommunityInviteUse::create([
                'invite_id' => $invite->id,
                'community_id' => $community->id,
                'user_id' => $user->id,
                'used_at' => now(),
            ]);

            return $member;
        });
    }

    public function requestJoin(User $user, Community $community, ?string $message = null): CommunityJoinRequest
    {
        if ($community->join_mode !== Community::JOIN_REQUEST) {
            throw new InvalidArgumentException('Community does not accept join requests.');
        }

        $existingPending = CommunityJoinRequest::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('status', CommunityJoinRequest::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            throw new InvalidArgumentException('User already has a pending join request for this community.');
        }

        return CommunityJoinRequest::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => CommunityJoinRequest::STATUS_PENDING,
            'message' => $message,
        ]);
    }

    public function approveJoinRequest(User $actor, CommunityJoinRequest $joinRequest): CommunityMember
    {
        $community = $joinRequest->community;

        if (! $this->policy->canApproveJoin($actor, $community)) {
            throw new InvalidArgumentException('Actor is not allowed to approve join requests.');
        }

        if ($joinRequest->status !== CommunityJoinRequest::STATUS_PENDING) {
            throw new InvalidArgumentException('Join request is not pending.');
        }

        return DB::transaction(function () use ($actor, $joinRequest, $community): CommunityMember {
            $joinRequest->update([
                'status' => CommunityJoinRequest::STATUS_APPROVED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $member = $this->insertMember($joinRequest->user, $community);

            $this->audit->log(
                $community,
                $actor,
                CommunityAuditLog::ACTION_JOIN_REQUEST_APPROVED,
                ['join_request_id' => $joinRequest->id],
                $joinRequest->user,
            );

            return $member;
        });
    }

    /**
     * Core member insertion. Must be called from within an open DB transaction.
     * Callers are responsible for transaction management.
     */
    private function insertMember(User $user, Community $community): CommunityMember
    {
        $existingMember = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if ($existingMember && $existingMember->status === CommunityMember::STATUS_ACTIVE) {
            throw new InvalidArgumentException('User is already an active member of this community.');
        }

        if ($community->member_limit !== null) {
            $current = Community::where('id', $community->id)->lockForUpdate()->value('member_count');
            if ($current >= $community->member_limit) {
                throw new RuntimeException('Community has reached its member limit.');
            }
        }

        $member = CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'status' => CommunityMember::STATUS_PENDING_KEY_DELIVERY,
            'joined_at' => now(),
        ]);

        $community->increment('member_count');

        CommunityUserState::firstOrCreate(
            ['community_id' => $community->id, 'user_id' => $user->id],
            [
                'notifications_enabled' => true,
                'muted' => false,
                'unread_posts_count' => 0,
                'pinned' => false,
                'last_read_community_seq' => 0,
            ],
        );

        $this->audit->log($community, $user, CommunityAuditLog::ACTION_MEMBER_JOINED);

        return $member;
    }
}
