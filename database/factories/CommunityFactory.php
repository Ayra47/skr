<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'avatar_path' => null,
            'cover_path' => null,
            'join_mode' => Community::JOIN_OPEN,
            'visibility' => Community::VISIBILITY_PUBLIC,
            'created_by' => User::factory(),
            'member_count' => 0,
            'post_count' => 0,
        ];
    }

    public function private(): static
    {
        return $this->state(['visibility' => Community::VISIBILITY_PRIVATE, 'join_mode' => Community::JOIN_INVITE_ONLY]);
    }

    public function requestRequired(): static
    {
        return $this->state(['join_mode' => Community::JOIN_REQUEST]);
    }
}
