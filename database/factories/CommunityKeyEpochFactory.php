<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityKeyEpoch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityKeyEpoch>
 */
class CommunityKeyEpochFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'epoch_number' => 1,
            'reason' => CommunityKeyEpoch::REASON_INITIAL,
        ];
    }
}
