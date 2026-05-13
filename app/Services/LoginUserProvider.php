<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

class LoginUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return User::find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $user = User::find($identifier);

        if (!$user) {
            return null;
        }

        return $user->rememberToken === $token ? $user : null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $user->setRememberToken($token);
        $user->save();
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (!isset($credentials['login'])) {
            return null;
        }

        return User::where('login', $credentials['login'])->first();
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return Hash::check($credentials['password'], $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Password rehashing not required for this implementation
    }
}
