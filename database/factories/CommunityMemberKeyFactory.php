<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityMemberKey;
use App\Models\User;
use App\Models\UserDeviceKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityMemberKey>
 */
class CommunityMemberKeyFactory extends Factory
{
    public function definition(): array
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();

        return [
            'community_id' => $community->id,
            'epoch_id' => CommunityKeyEpoch::factory()->for($community),
            'user_id' => $user->id,
            'device_key_id' => UserDeviceKey::factory()->for($user),
            'encrypted_key' => base64_encode(fake()->sha256()),
        ];
    }
}
