<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('chat.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('presence-chat', function (User $user) {
    return ['id' => $user->id, 'login' => $user->login];
});

Broadcast::channel('poll.{pollId}', fn () => true);
