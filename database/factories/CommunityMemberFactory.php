<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityMember>
 */
class CommunityMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'role' => CommunityMember::ROLE_MEMBER,
            'status' => CommunityMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => CommunityMember::ROLE_OWNER]);
    }

    public function admin(): static
    {
        return $this->state(['role' => CommunityMember::ROLE_ADMIN]);
    }

    public function moderator(): static
    {
        return $this->state(['role' => CommunityMember::ROLE_MODERATOR]);
    }

    public function pendingKeyDelivery(): static
    {
        return $this->state(['status' => CommunityMember::STATUS_PENDING_KEY_DELIVERY]);
    }

    public function banned(): static
    {
        return $this->state(fn () => [
            'status' => CommunityMember::STATUS_BANNED,
            'banned_at' => now(),
            'ban_reason_code' => CommunityMember::BAN_REASON_RULE_VIOLATION,
        ]);
    }
}
