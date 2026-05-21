<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\CommunityInvite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CommunityInviteService
{
    public function __construct(
        private readonly CommunityPolicyService $policy,
        private readonly CommunityAuditService $audit,
    ) {}

    public function generateInvite(
        User $actor,
        Community $community,
        ?int $maxUses = null,
        ?Carbon $expiresAt = null,
    ): CommunityInvite {
        if (! $this->policy->canInvite($actor, $community)) {
            throw new InvalidArgumentException('User is not allowed to create invites for this community.');
        }

        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'created_by' => $actor->id,
            'code' => strtoupper(Str::random(10)),
            'max_uses' => $maxUses,
            'use_count' => 0,
            'is_revoked' => false,
            'expires_at' => $expiresAt,
        ]);

        $this->audit->log($community, $actor, CommunityAuditLog::ACTION_INVITE_CREATED, [
            'invite_id' => $invite->id,
            'max_uses' => $maxUses,
        ]);

        return $invite;
    }

    public function revokeInvite(User $actor, CommunityInvite $invite): void
    {
        $community = $invite->community;

        if (! $this->policy->canInvite($actor, $community)) {
            throw new InvalidArgumentException('User is not allowed to revoke invites for this community.');
        }

        $invite->update([
            'is_revoked' => true,
            'revoked_at' => now(),
        ]);

        $this->audit->log($community, $actor, CommunityAuditLog::ACTION_INVITE_REVOKED, [
            'invite_id' => $invite->id,
        ]);
    }
}
