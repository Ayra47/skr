<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDeviceKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDeviceKey>
 */
class UserDeviceKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_label' => fake()->optional()->words(2, true),
            'device_identifier' => fake()->unique()->sha1(),
            'public_key' => base64_encode(fake()->sha256()),
            'fingerprint' => fake()->sha1(),
            'last_seen_at' => null,
            'revoked_at' => null,
        ];
    }
}
