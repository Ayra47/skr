<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTopic;
use App\Models\User;

final class CommunityPolicyService
{
    private const ROLE_HIERARCHY = [
        CommunityMember::ROLE_MEMBER => 0,
        CommunityMember::ROLE_MODERATOR => 1,
        CommunityMember::ROLE_ADMIN => 2,
        CommunityMember::ROLE_OWNER => 3,
    ];

    public function getMembership(User $user, Community $community): ?CommunityMember
    {
        return CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function isActiveMember(User $user, Community $community): bool
    {
        return CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('status', CommunityMember::STATUS_ACTIVE)
            ->exists();
    }

    public function roleAtLeast(CommunityMember $member, string $minimumRole): bool
    {
        $memberLevel = self::ROLE_HIERARCHY[$member->role] ?? -1;
        $requiredLevel = self::ROLE_HIERARCHY[$minimumRole] ?? PHP_INT_MAX;

        return $memberLevel >= $requiredLevel;
    }

    public function canInvite(User $user, Community $community): bool
    {
        $membership = $this->getMembership($user, $community);

        if ($membership === null || $membership->status !== CommunityMember::STATUS_ACTIVE) {
            return false;
        }

        if ($community->invite_policy === Community::INVITE_POLICY_ALL_MEMBERS) {
            return true;
        }

        return $this->roleAtLeast($membership, CommunityMember::ROLE_MODERATOR);
    }

    public function canApproveJoin(User $user, Community $community): bool
    {
        $membership = $this->getMembership($user, $community);

        if ($membership === null || $membership->status !== CommunityMember::STATUS_ACTIVE) {
            return false;
        }

        return $this->roleAtLeast($membership, CommunityMember::ROLE_MODERATOR);
    }

    public function canPostInTopic(User $user, CommunityTopic $topic): bool
    {
        $community = $topic->community;

        if ($topic->is_archived) {
            return false;
        }

        $membership = $this->getMembership($user, $community);

        if ($membership === null || $membership->status !== CommunityMember::STATUS_ACTIVE) {
            return false;
        }

        // Topic-level policy takes precedence; fall back to community-level policy.
        $effectivePolicy = $topic->posting_policy ?? $community->posting_policy;

        if ($effectivePolicy === Community::POSTING_POLICY_MODERATORS_ONLY) {
            return $this->roleAtLeast($membership, CommunityMember::ROLE_MODERATOR);
        }

        return true;
    }

    public function canManageTopic(User $user, Community $community): bool
    {
        $membership = $this->getMembership($user, $community);

        if ($membership === null || $membership->status !== CommunityMember::STATUS_ACTIVE) {
            return false;
        }

        return $this->roleAtLeast($membership, CommunityMember::ROLE_MODERATOR);
    }

    /**
     * Public communities are visible to any authenticated user.
     * Private and hidden communities require active membership.
     */
    public function canViewCommunity(User $user, Community $community): bool
    {
        if ($community->visibility === Community::VISIBILITY_PUBLIC) {
            return true;
        }

        return $this->isActiveMember($user, $community);
    }
}
