<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\CommunityDirectInvite;
use App\Models\CommunityMember;
use App\Models\CommunityUserState;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class CommunityDirectInviteService
{
    public function __construct(
        private readonly CommunityPolicyService $policy,
        private readonly CommunityAuditService $audit,
    ) {}

    public function sendInvite(
        User $inviter,
        Community $community,
        User $invitee,
        ?string $message = null,
        ?Carbon $expiresAt = null,
    ): CommunityDirectInvite {
        if ($inviter->id === $invitee->id) {
            throw new InvalidArgumentException('User cannot invite themselves.');
        }

        if (! $this->policy->canInvite($inviter, $community)) {
            throw new InvalidArgumentException('User is not allowed to invite members to this community.');
        }

        if (! $inviter->isFriendWith($invitee->id)) {
            throw new InvalidArgumentException('Direct community invites can only be sent to friends.');
        }

        $existingMember = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $invitee->id)
            ->whereIn('status', [
                CommunityMember::STATUS_ACTIVE,
                CommunityMember::STATUS_PENDING_KEY_DELIVERY,
            ])
            ->exists();

        if ($existingMember) {
            throw new InvalidArgumentException('Invitee is already a member or pending key delivery.');
        }

        $existingPendingInvite = CommunityDirectInvite::where('community_id', $community->id)
            ->where('invitee_id', $invitee->id)
            ->where('status', CommunityDirectInvite::STATUS_PENDING)
            ->exists();

        if ($existingPendingInvite) {
            throw new InvalidArgumentException('Invitee already has a pending direct invite for this community.');
        }

        $invite = CommunityDirectInvite::create([
            'community_id' => $community->id,
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'status' => CommunityDirectInvite::STATUS_PENDING,
            'message' => $message,
            'expires_at' => $expiresAt,
        ]);

        $this->audit->log($community, $inviter, CommunityAuditLog::ACTION_DIRECT_INVITE_CREATED, [
            'direct_invite_id' => $invite->id,
            'invitee_id' => $invitee->id,
        ], $invitee);

        return $invite;
    }

    public function acceptInvite(User $invitee, CommunityDirectInvite $invite): CommunityMember
    {
        if ($invite->invitee_id !== $invitee->id) {
            throw new InvalidArgumentException('Only the invitee can accept this invite.');
        }

        return DB::transaction(function () use ($invitee, $invite): CommunityMember {
            $lockedInvite = CommunityDirectInvite::whereKey($invite->id)->lockForUpdate()->firstOrFail();

            if (! $lockedInvite->isAcceptable()) {
                throw new InvalidArgumentException('Direct invite is not pending or has expired.');
            }

            $community = Community::whereKey($lockedInvite->community_id)->lockForUpdate()->firstOrFail();

            $existingMember = CommunityMember::where('community_id', $community->id)
                ->where('user_id', $invitee->id)
                ->lockForUpdate()
                ->first();

            if ($existingMember && in_array($existingMember->status, [
                CommunityMember::STATUS_ACTIVE,
                CommunityMember::STATUS_PENDING_KEY_DELIVERY,
            ], true)) {
                throw new InvalidArgumentException('User is already a member or pending key delivery.');
            }

            if ($community->member_limit !== null && $community->member_count >= $community->member_limit) {
                throw new RuntimeException('Community has reached its member limit.');
            }

            $member = CommunityMember::create([
                'community_id' => $community->id,
                'user_id' => $invitee->id,
                'role' => CommunityMember::ROLE_MEMBER,
                'status' => CommunityMember::STATUS_PENDING_KEY_DELIVERY,
                'joined_at' => now(),
            ]);

            $community->increment('member_count');

            CommunityUserState::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $invitee->id],
                [
                    'notifications_enabled' => true,
                    'muted' => false,
                    'unread_posts_count' => 0,
                    'pinned' => false,
                    'last_read_community_seq' => 0,
                ],
            );

            $lockedInvite->update([
                'status' => CommunityDirectInvite::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            $this->audit->log($community, $invitee, CommunityAuditLog::ACTION_DIRECT_INVITE_ACCEPTED, [
                'direct_invite_id' => $lockedInvite->id,
            ], $invitee);

            return $member;
        });
    }

    public function declineInvite(User $invitee, CommunityDirectInvite $invite): void
    {
        if ($invite->invitee_id !== $invitee->id) {
            throw new InvalidArgumentException('Only the invitee can decline this invite.');
        }

        if (! $invite->isPending()) {
            throw new InvalidArgumentException('Direct invite is not pending.');
        }

        $invite->update([
            'status' => CommunityDirectInvite::STATUS_DECLINED,
            'responded_at' => now(),
        ]);

        $this->audit->log($invite->community, $invitee, CommunityAuditLog::ACTION_DIRECT_INVITE_DECLINED, [
            'direct_invite_id' => $invite->id,
        ], $invitee);
    }

    public function cancelInvite(User $actor, CommunityDirectInvite $invite): void
    {
        if (! $invite->isPending()) {
            throw new InvalidArgumentException('Direct invite is not pending.');
        }

        $community = $invite->community;
        $membership = $this->policy->getMembership($actor, $community);
        $canModerate = $membership !== null
            && $membership->status === CommunityMember::STATUS_ACTIVE
            && $this->policy->roleAtLeast($membership, CommunityMember::ROLE_MODERATOR);

        if ($invite->inviter_id !== $actor->id && ! $canModerate) {
            throw new InvalidArgumentException('Actor is not allowed to cancel this direct invite.');
        }

        $invite->update([
            'status' => CommunityDirectInvite::STATUS_CANCELLED,
            'responded_at' => now(),
        ]);

        $this->audit->log($community, $actor, CommunityAuditLog::ACTION_DIRECT_INVITE_CANCELLED, [
            'direct_invite_id' => $invite->id,
        ], $invite->invitee);
    }

    /**
     * @return Collection<int, CommunityDirectInvite>
     */
    public function listPendingForUser(User $user): Collection
    {
        return CommunityDirectInvite::query()
            ->with(['inviter', 'community'])
            ->where('invitee_id', $user->id)
            ->where('status', CommunityDirectInvite::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get();
    }
}
