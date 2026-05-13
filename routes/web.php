<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatFileController;
use App\Http\Controllers\FriendsController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Verification links — accessible without auth (user may open on different device/browser)
Route::get('/settings/email/verify', [SettingsController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('settings.email.verify');

Route::get('/settings/password/verify', [SettingsController::class, 'verifyPasswordChange'])
    ->middleware('signed')
    ->name('settings.password.verify');

Route::get('/settings/email/detach/confirm', [SettingsController::class, 'confirmDetachEmail'])
    ->middleware('signed')
    ->name('settings.email.detach.confirm');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/login/backup', [LoginController::class, 'loginWithBackupCode'])->name('login.backup');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    // Forgot password
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('forgot-password');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('forgot-password.send');
    Route::get('/password/reset', [ForgotPasswordController::class, 'showReset'])->middleware('signed')->name('password.reset');
    Route::post('/password/reset', [ForgotPasswordController::class, 'reset'])->name('password.reset.submit');
});

// 2FA — session-guarded (user pending 2FA, not yet fully authenticated)
Route::get('/2fa', [TwoFactorController::class, 'show'])->name('2fa.show');
Route::post('/2fa', [TwoFactorController::class, 'verify'])->name('2fa.verify');
Route::post('/2fa/resend', [TwoFactorController::class, 'resend'])->name('2fa.resend');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
    Route::get('/', function () {
        return 'home';
    })->name('home');

    // Friends routes
    Route::get('/friends', [FriendsController::class, 'index'])->name('friends.index');
    Route::post('/friends/code', [FriendsController::class, 'createCode'])->name('friends.code');
    Route::post('/friends/search', [FriendsController::class, 'searchByCode'])->name('friends.search');
    Route::post('/friends/request', [FriendsController::class, 'sendRequest'])->name('friends.request');
    Route::post('/friends/request/accept', [FriendsController::class, 'acceptRequest'])->name('friends.request.accept');
    Route::post('/friends/request/decline', [FriendsController::class, 'declineRequest'])->name('friends.request.decline');
    Route::post('/friends/remove', [FriendsController::class, 'removeFriend'])->name('friends.remove');
    Route::get('/friends/unread', [FriendsController::class, 'getUnreadCount'])->name('friends.unread');
    Route::post('/friends/read', [FriendsController::class, 'markAsRead'])->name('friends.read');
    Route::match(['head'], '/friends/time', [FriendsController::class, 'syncTime'])->name('friends.time');

    // Settings routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::post('/settings/email/resend', [SettingsController::class, 'resendEmailVerification'])->name('settings.email.resend');
    Route::delete('/settings/email', [SettingsController::class, 'detachEmail'])->name('settings.email.detach');
    Route::post('/settings/avatar', [SettingsController::class, 'updateAvatar'])->name('settings.avatar.update');
    Route::delete('/settings/avatar', [SettingsController::class, 'deleteAvatar'])->name('settings.avatar.delete');
    Route::post('/settings/password', [SettingsController::class, 'changePassword'])->name('settings.password.update');
    Route::post('/settings/backup-code', [SettingsController::class, 'storeBackupCodeHash'])->name('settings.backup-code.store');
    Route::post('/settings/two-factor', [SettingsController::class, 'updateTwoFactor'])->name('settings.two-factor.update');
    Route::get('/settings/notifications', [SettingsController::class, 'getNotificationPrefs'])->name('settings.notifications.get');
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotificationPrefs'])->name('settings.notifications.update');
    Route::post('/ping', [SettingsController::class, 'heartbeat'])->name('ping');
    Route::post('/push/subscription', [PushSubscriptionController::class, 'store'])->name('push.subscription.store');
    Route::delete('/push/subscription', [PushSubscriptionController::class, 'destroy'])->name('push.subscription.destroy');

    // Chat routes
    Route::redirect('/chat', '/')->name('chat.index');
    Route::post('/chat/conversation', [ChatController::class, 'startConversation'])->name('chat.conversation.start');
    Route::post('/chat/keys', [ChatController::class, 'storePublicKey'])->name('chat.keys.store');
    Route::get('/chat/keys/backup', [ChatController::class, 'getKeyBackup'])->name('chat.keys.backup.show');
    Route::post('/chat/keys/backup', [ChatController::class, 'storeKeyBackup'])->name('chat.keys.backup.store');
    Route::get('/chat/keys/{userId}', [ChatController::class, 'getPublicKey'])->name('chat.keys.show');
    Route::get('/chat/settings', [ChatController::class, 'getSettings'])->name('chat.settings');
    Route::post('/chat/settings', [ChatController::class, 'storeSettings'])->name('chat.settings.store');
    Route::get('/chat/{conversationId}/messages', [ChatController::class, 'messages'])->name('chat.messages.index');
    Route::post('/chat/{conversationId}/messages', [ChatController::class, 'store'])->name('chat.messages.store');
    Route::patch('/chat/{conversationId}/messages/{messageId}', [ChatController::class, 'update'])->name('chat.messages.update');
    Route::delete('/chat/{conversationId}/messages/{messageId}', [ChatController::class, 'destroy'])->name('chat.messages.destroy');
    Route::get('/chat/{conversationId}/messages/{messageId}/edits', [ChatController::class, 'messageEdits'])->name('chat.messages.edits');
    Route::post('/chat/messages/delivered', [ChatController::class, 'markDelivered'])->name('chat.messages.delivered');
    Route::post('/chat/messages/read', [ChatController::class, 'markRead'])->name('chat.messages.read');
    Route::post('/chat/typing', [ChatController::class, 'typing'])->name('chat.typing');

    // File upload/download routes
    Route::post('/chat/{conversationId}/files', [ChatFileController::class, 'uploadChunk'])->name('chat.files.upload');
    Route::get('/chat/files/{fileUuid}', [ChatFileController::class, 'download'])->name('chat.files.download');

    Route::post('/chat/{conversationId}/location', [LocationController::class, 'store'])->name('chat.location.store');
    Route::post('/chat/{conversationId}/location/{sessionUuid}/position', [LocationController::class, 'updatePosition'])->name('chat.location.position');
    Route::delete('/chat/{conversationId}/location/{sessionUuid}', [LocationController::class, 'stop'])->name('chat.location.stop');
    Route::get('/chat/{conversationId}/location/{sessionUuid}', [LocationController::class, 'show'])->name('chat.location.show');
});
