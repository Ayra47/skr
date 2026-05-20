<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityTopic;
use App\Models\CommunityTopicUserState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityTopicUserState>
 */
class CommunityTopicUserStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'topic_id' => CommunityTopic::factory(),
            'user_id' => User::factory(),
            'muted' => false,
            'notifications_enabled' => true,
            'unread_count' => 0,
            'last_read_post_id' => null,
        ];
    }
}
