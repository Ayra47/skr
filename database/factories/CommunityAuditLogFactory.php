<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityAuditLog>
 */
class CommunityAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'actor_id' => User::factory(),
            'target_user_id' => null,
            'action' => fake()->randomElement(['member_added', 'member_removed', 'role_changed', 'settings_updated']),
            'payload' => null,
        ];
    }
}
