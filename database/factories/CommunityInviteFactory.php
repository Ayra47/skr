<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunityInvite>
 */
class CommunityInviteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'created_by' => User::factory(),
            'code' => strtoupper(Str::random(10)),
            'max_uses' => null,
            'use_count' => 0,
            'is_revoked' => false,
            'revoked_at' => null,
            'expires_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(['is_revoked' => true, 'revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }
}
