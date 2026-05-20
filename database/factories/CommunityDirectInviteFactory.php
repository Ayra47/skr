<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityDirectInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityDirectInvite>
 */
class CommunityDirectInviteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'inviter_id' => User::factory(),
            'invitee_id' => User::factory(),
            'status' => CommunityDirectInvite::STATUS_PENDING,
            'message' => fake()->optional()->sentence(),
            'expires_at' => null,
            'responded_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => CommunityDirectInvite::STATUS_PENDING,
            'responded_at' => null,
            'expires_at' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => CommunityDirectInvite::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state([
            'status' => CommunityDirectInvite::STATUS_DECLINED,
            'responded_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => CommunityDirectInvite::STATUS_CANCELLED,
            'responded_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => CommunityDirectInvite::STATUS_EXPIRED,
            'expires_at' => now()->subHour(),
            'responded_at' => null,
        ]);
    }
}
