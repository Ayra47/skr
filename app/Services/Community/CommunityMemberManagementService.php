<?php

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CommunityMemberManagementService
{
    public function changeRole(CommunityMember $member, string $role): CommunityMember
    {
        if (! in_array($role, [
            CommunityMember::ROLE_OWNER,
            CommunityMember::ROLE_ADMIN,
            CommunityMember::ROLE_MODERATOR,
            CommunityMember::ROLE_MEMBER,
        ], true)) {
            throw new InvalidArgumentException('Invalid community role.');
        }

        return DB::transaction(function () use ($member, $role): CommunityMember {
            $locked = CommunityMember::whereKey($member->id)->lockForUpdate()->firstOrFail();

            if ($locked->role === CommunityMember::ROLE_OWNER && $role !== CommunityMember::ROLE_OWNER) {
                $this->ensureNotLastActiveOwner($locked);
            }

            $locked->update(['role' => $role]);

            return $locked;
        });
    }

    public function leaveCommunity(User $user, Community $community): CommunityMember
    {
        return DB::transaction(function () use ($user, $community): CommunityMember {
            $member = CommunityMember::query()
                ->where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->deactivate($member, CommunityMember::STATUS_LEFT);
        });
    }

    public function removeMember(CommunityMember $member, string $status = CommunityMember::STATUS_LEFT): CommunityMember
    {
        if (! in_array($status, [
            CommunityMember::STATUS_LEFT,
            CommunityMember::STATUS_BANNED,
            CommunityMember::STATUS_SUSPENDED,
        ], true)) {
            throw new InvalidArgumentException('Invalid community member removal status.');
        }

        return DB::transaction(function () use ($member, $status): CommunityMember {
            $locked = CommunityMember::whereKey($member->id)->lockForUpdate()->firstOrFail();

            return $this->deactivate($locked, $status);
        });
    }

    private function deactivate(CommunityMember $member, string $status): CommunityMember
    {
        if ($member->role === CommunityMember::ROLE_OWNER) {
            $this->ensureNotLastActiveOwner($member);
        }

        $wasActive = $member->status === CommunityMember::STATUS_ACTIVE;
        $timestamps = match ($status) {
            CommunityMember::STATUS_LEFT => ['left_at' => now()],
            CommunityMember::STATUS_BANNED => ['banned_at' => now()],
            default => [],
        };

        $member->update([
            'status' => $status,
            ...$timestamps,
        ]);

        if ($wasActive) {
            $community = Community::whereKey($member->community_id)->lockForUpdate()->firstOrFail();
            $community->forceFill([
                'member_count' => max(0, $community->member_count - 1),
            ])->save();
        }

        return $member;
    }

    private function ensureNotLastActiveOwner(CommunityMember $member): void
    {
        if ($member->status !== CommunityMember::STATUS_ACTIVE) {
            return;
        }

        $hasAnotherActiveOwner = CommunityMember::query()
            ->where('community_id', $member->community_id)
            ->whereKeyNot($member->id)
            ->where('role', CommunityMember::ROLE_OWNER)
            ->where('status', CommunityMember::STATUS_ACTIVE)
            ->lockForUpdate()
            ->exists();

        if (! $hasAnotherActiveOwner) {
            throw new InvalidArgumentException('The last active community owner cannot be removed, downgraded, or leave.');
        }
    }
}
