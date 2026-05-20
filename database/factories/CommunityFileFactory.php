<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityFile>
 */
class CommunityFileFactory extends Factory
{
    public function definition(): array
    {
        $ext = fake()->randomElement(['jpg', 'png', 'pdf', 'mp4']);

        return [
            'community_id' => Community::factory(),
            'post_id' => null,
            'uploaded_by' => User::factory(),
            'path' => 'community-files/'.fake()->uuid().'.'.$ext,
            'thumbnail_path' => null,
            'name' => fake()->word().'.'.$ext,
            'mime' => fake()->mimeType(),
            'size' => fake()->numberBetween(1024, 10_000_000),
            'position' => 1,
        ];
    }
}
