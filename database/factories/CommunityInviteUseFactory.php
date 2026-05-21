<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityInviteUse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityInviteUse>
 */
class CommunityInviteUseFactory extends Factory
{
    public function definition(): array
    {
        $community = Community::factory()->create();

        return [
            'invite_id' => CommunityInvite::factory()->for($community),
            'community_id' => $community->id,
            'user_id' => User::factory(),
            'used_at' => now(),
            'ip_hash' => null,
            'user_agent_hash' => null,
        ];
    }
}
