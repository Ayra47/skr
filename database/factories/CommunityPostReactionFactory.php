<?php

namespace Database\Factories;

use App\Models\CommunityPost;
use App\Models\CommunityPostReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityPostReaction>
 */
class CommunityPostReactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => CommunityPost::factory(),
            'user_id' => User::factory(),
            'emoji' => fake()->randomElement(['👍', '❤️', '😂', '😮', '😢']),
        ];
    }
}
