<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityPost>
 */
class CommunityPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'topic_id' => null,
            'user_id' => User::factory(),
            'epoch_id' => null,
            'ciphertext' => base64_encode(fake()->sha256()),
            'nonce' => base64_encode(fake()->sha1()),
            'community_seq' => fake()->numberBetween(1, 10000),
            'topic_seq' => fake()->numberBetween(1, 10000),
            'visibility' => CommunityPost::VISIBILITY_MEMBERS_ONLY,
            'moderation_status' => CommunityPost::MODERATION_VISIBLE,
            'is_pinned' => false,
            'reaction_count' => 0,
            'comment_count' => 0,
            'reply_count' => 0,
            'attachments_count' => 0,
            'expires_at' => null,
            'ttl_seconds' => null,
            'client_idempotency_key' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(['visibility' => CommunityPost::VISIBILITY_PUBLIC]);
    }

    public function private(): static
    {
        return $this->state(['visibility' => CommunityPost::VISIBILITY_PRIVATE]);
    }

    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }
}
