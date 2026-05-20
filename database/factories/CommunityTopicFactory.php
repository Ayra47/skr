<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunityTopic>
 */
class CommunityTopicFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'community_id' => Community::factory(),
            'name' => ucfirst($name),
            'description' => fake()->optional()->sentence(),
            'slug' => Str::slug($name).'-'.fake()->numerify('##'),
            'type' => 'regular',
            'posting_policy' => null,
            'sort_order' => 0,
            'created_by' => User::factory(),
            'post_count' => 0,
            'is_system' => false,
            'is_pinned' => false,
            'is_archived' => false,
            'archived_at' => null,
        ];
    }
}
