<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityMember;
use App\Models\CommunityMemberKey;
use App\Models\User;
use App\Models\UserDeviceKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CommunityKeyDeliveryService
{
    public function __construct(
        private readonly CommunityPolicyService $policy,
        private readonly CommunityAuditService $audit,
    ) {}

    public function pendingMembers(Community $community): Collection
    {
        return CommunityMember::where('community_id', $community->id)
            ->where('status', CommunityMember::STATUS_PENDING_KEY_DELIVERY)
            ->get();
    }

    /**
     * @param  array<array{device_key_id: string, encrypted_key: string}>  $deviceEncryptedKeys
     */
    public function deliverMemberKeys(
        User $actor,
        CommunityMember $member,
        array $deviceEncryptedKeys,
    ): void {
        $community = $member->community;

        $actorMembership = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $actor->id)
            ->where('status', CommunityMember::STATUS_ACTIVE)
            ->first();

        if ($actorMembership === null || ! $this->policy->roleAtLeast($actorMembership, CommunityMember::ROLE_MODERATOR)) {
            throw new InvalidArgumentException('Actor is not authorised to deliver keys in this community.');
        }
        $epoch = CommunityKeyEpoch::where('community_id', $community->id)
            ->orderByDesc('epoch_number')
            ->firstOrFail();

        DB::transaction(function () use ($actor, $member, $epoch, $community, $deviceEncryptedKeys): void {
            foreach ($deviceEncryptedKeys as $entry) {
                $deviceKeyId = $entry['device_key_id'];
                $encryptedKey = $entry['encrypted_key'];

                $deviceKey = UserDeviceKey::where('id', $deviceKeyId)
                    ->where('user_id', $member->user_id)
                    ->whereNull('revoked_at')
                    ->first();

                if ($deviceKey === null) {
                    throw new InvalidArgumentException(
                        "Device key {$deviceKeyId} does not belong to the member's user or is revoked."
                    );
                }

                CommunityMemberKey::create([
                    'community_id' => $community->id,
                    'epoch_id' => $epoch->id,
                    'user_id' => $member->user_id,
                    'device_key_id' => $deviceKeyId,
                    'encrypted_key' => $encryptedKey,
                ]);
            }

            $this->audit->log(
                $community,
                $actor,
                CommunityAuditLog::ACTION_KEY_DELIVERED,
                ['epoch_id' => $epoch->id, 'device_count' => count($deviceEncryptedKeys)],
                $member->user,
            );
        });
    }

    public function activateMemberIfAllDeviceKeysDelivered(CommunityMember $member): bool
    {
        $community = $member->community;
        $epoch = CommunityKeyEpoch::where('community_id', $community->id)
            ->orderByDesc('epoch_number')
            ->first();

        if ($epoch === null) {
            return false;
        }

        $activeDeviceKeyIds = UserDeviceKey::where('user_id', $member->user_id)
            ->whereNull('revoked_at')
            ->pluck('id');

        if ($activeDeviceKeyIds->isEmpty()) {
            return false;
        }

        $deliveredKeyIds = CommunityMemberKey::where('community_id', $community->id)
            ->where('epoch_id', $epoch->id)
            ->where('user_id', $member->user_id)
            ->pluck('device_key_id');

        $allDelivered = $activeDeviceKeyIds->diff($deliveredKeyIds)->isEmpty();

        if ($allDelivered) {
            $member->update(['status' => CommunityMember::STATUS_ACTIVE]);
        }

        return $allDelivered;
    }
}
