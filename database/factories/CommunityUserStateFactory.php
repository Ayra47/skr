<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityUserState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityUserState>
 */
class CommunityUserStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'notifications_enabled' => true,
            'muted' => false,
            'unread_posts_count' => 0,
            'last_visited_at' => null,
            'pinned' => false,
            'last_read_community_seq' => 0,
            'last_activity_seen_at' => null,
        ];
    }
}
