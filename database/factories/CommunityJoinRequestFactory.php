<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityJoinRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityJoinRequest>
 */
class CommunityJoinRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'status' => CommunityJoinRequest::STATUS_PENDING,
            'message' => fake()->optional()->sentence(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => CommunityJoinRequest::STATUS_APPROVED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => CommunityJoinRequest::STATUS_REJECTED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
