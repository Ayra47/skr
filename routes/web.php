<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatFileController;
use App\Http\Controllers\CommunitiesController;
use App\Http\Controllers\Community\CommunityController;
use App\Http\Controllers\Community\CommunityDirectInviteController;
use App\Http\Controllers\Community\CommunityInviteController;
use App\Http\Controllers\Community\CommunityJoinController;
use App\Http\Controllers\Community\CommunityKeyDeliveryController;
use App\Http\Controllers\Community\CommunityPostController;
use App\Http\Controllers\Community\CommunityReadStateController;
use App\Http\Controllers\Community\CommunityTopicController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FriendsController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatusController;
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

Route::get('/friends/join/{code}', [FriendsController::class, 'joinByCode'])->name('friends.join');

Route::middleware('auth')->group(function () {
    Route::get('/communities', [CommunitiesController::class, 'index'])->name('communities.index');

    // Community API routes
    Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
    Route::get('/communities/invitations', [CommunityDirectInviteController::class, 'index'])->name('communities.invitations.index');
    Route::get('/communities/{community}', [CommunityController::class, 'show'])->name('communities.show');

    Route::post('/communities/{community}/invites', [CommunityInviteController::class, 'store'])->name('communities.invites.store');
    Route::delete('/communities/invites/{invite}', [CommunityInviteController::class, 'destroy'])->name('communities.invites.destroy');

    Route::post('/communities/{community}/direct-invites', [CommunityDirectInviteController::class, 'store'])->name('communities.direct-invites.store');
    Route::post('/communities/direct-invites/{invite}/accept', [CommunityDirectInviteController::class, 'accept'])->name('communities.direct-invites.accept');
    Route::post('/communities/direct-invites/{invite}/decline', [CommunityDirectInviteController::class, 'decline'])->name('communities.direct-invites.decline');
    Route::post('/communities/direct-invites/{invite}/cancel', [CommunityDirectInviteController::class, 'cancel'])->name('communities.direct-invites.cancel');

    Route::post('/communities/join-by-invite', [CommunityJoinController::class, 'joinByInvite'])->name('communities.join-by-invite');
    Route::post('/communities/{community}/join', [CommunityJoinController::class, 'joinPublic'])->name('communities.join');
    Route::post('/communities/{community}/join-requests', [CommunityJoinController::class, 'requestJoin'])->name('communities.join-requests.store');
    Route::post('/communities/join-requests/{joinRequest}/approve', [CommunityJoinController::class, 'approveJoinRequest'])->name('communities.join-requests.approve');
    Route::post('/communities/join-requests/{joinRequest}/reject', [CommunityJoinController::class, 'rejectJoinRequest'])->name('communities.join-requests.reject');

    Route::post('/communities/{community}/topics', [CommunityTopicController::class, 'store'])->name('communities.topics.store');
    Route::patch('/communities/topics/{topic}/archive', [CommunityTopicController::class, 'archive'])->name('communities.topics.archive');

    Route::post('/communities/{community}/topics/{topic}/posts', [CommunityPostController::class, 'store'])
        ->scopeBindings()
        ->name('communities.topics.posts.store');

    Route::post('/communities/members/{member}/keys', [CommunityKeyDeliveryController::class, 'store'])->name('communities.members.keys.store');

    Route::post('/communities/topics/{topic}/mark-read', [CommunityReadStateController::class, 'markTopicRead'])->name('communities.topics.mark-read');
    Route::post('/communities/{community}/mark-read', [CommunityReadStateController::class, 'markCommunityRead'])->name('communities.mark-read');
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::delete('/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
    Route::get('/bookmarks/{bookmark}/attachments/{attachment}', [BookmarkController::class, 'attachment'])->name('bookmarks.attachments.show');

    Route::get('/status', [StatusController::class, 'index'])->name('status.index');
    Route::get('/status/incidents/more', [StatusController::class, 'moreIncidents'])->name('status.incidents.more');
    Route::get('/status/canary/more', [StatusController::class, 'moreCanary'])->name('status.canary.more');

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profiles.show');
    Route::post('/notifications/{id}/read', [NotificationsController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationsController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/', [FeedController::class, 'index'])->name('feed.index');
    Route::post('/feed/posts', [FeedController::class, 'store'])->name('feed.posts.store');
    Route::delete('/feed/posts/{post}', [FeedController::class, 'destroy'])->name('feed.posts.destroy');
    Route::post('/feed/posts/{post}/vote', [FeedController::class, 'vote'])->name('feed.posts.vote');
    Route::post('/feed/posts/{post}/poll/vote', [PollController::class, 'vote'])->name('feed.posts.poll.vote');
    Route::delete('/feed/posts/{post}/poll/vote', [PollController::class, 'cancelVote'])->name('feed.posts.poll.vote.cancel');
    Route::post('/feed/posts/{post}/comments', [FeedController::class, 'comment'])->name('feed.posts.comments.store');
    Route::get('/feed/posts/{post}/attachments/{attachment}', [FeedController::class, 'attachment'])
        ->scopeBindings()
        ->name('feed.posts.attachments.show');
    Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');

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
    Route::post('/settings/profile/visibility', [SettingsController::class, 'updateProfileVisibility'])->name('settings.profile-visibility.update');
    Route::post('/settings/email/resend', [SettingsController::class, 'resendEmailVerification'])->name('settings.email.resend');
    Route::delete('/settings/email', [SettingsController::class, 'detachEmail'])->name('settings.email.detach');
    Route::post('/settings/avatar', [SettingsController::class, 'updateAvatar'])->name('settings.avatar.update');
    Route::delete('/settings/avatar', [SettingsController::class, 'deleteAvatar'])->name('settings.avatar.delete');
    Route::post('/settings/password', [SettingsController::class, 'changePassword'])->name('settings.password.update');
    Route::post('/settings/backup-code', [SettingsController::class, 'storeBackupCodeHash'])->name('settings.backup-code.store');
    Route::post('/settings/two-factor', [SettingsController::class, 'updateTwoFactor'])->name('settings.two-factor.update');
    Route::get('/settings/notifications', [SettingsController::class, 'getNotificationPrefs'])->name('settings.notifications.get');
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotificationPrefs'])->name('settings.notifications.update');
    Route::post('/settings/accent', [SettingsController::class, 'updateAccentColor'])->name('settings.accent.update');
    Route::post('/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme.update');
    Route::post('/ping', [SettingsController::class, 'heartbeat'])->name('ping');
    Route::post('/push/subscription', [PushSubscriptionController::class, 'store'])->name('push.subscription.store');
    Route::delete('/push/subscription', [PushSubscriptionController::class, 'destroy'])->name('push.subscription.destroy');

    // Chat routes
    Route::redirect('/chat', '/')->name('chat.index');
    Route::post('/chat/conversation', [ChatController::class, 'startConversation'])->name('chat.conversation.start');
    Route::post('/chat/groups', [ChatController::class, 'createGroup'])->name('chat.groups.store');
    Route::get('/chat/invite/{token}', [ChatController::class, 'joinByInvite'])->name('chat.invites.join');
    Route::post('/chat/group-requests/{joinRequestId}/accept', [ChatController::class, 'acceptJoinRequest'])->name('chat.group-requests.accept');
    Route::delete('/chat/group-requests/{joinRequestId}', [ChatController::class, 'declineJoinRequest'])->name('chat.group-requests.decline');
    Route::post('/chat/keys', [ChatController::class, 'storePublicKey'])->name('chat.keys.store');
    Route::get('/chat/keys/backup', [ChatController::class, 'getKeyBackup'])->name('chat.keys.backup.show');
    Route::post('/chat/keys/backup', [ChatController::class, 'storeKeyBackup'])->name('chat.keys.backup.store');
    Route::get('/chat/keys/{userId}', [ChatController::class, 'getPublicKey'])->name('chat.keys.show');
    Route::get('/chat/settings', [ChatController::class, 'getSettings'])->name('chat.settings');
    Route::post('/chat/settings', [ChatController::class, 'storeSettings'])->name('chat.settings.store');
    Route::get('/chat/{conversationId}/participants', [ChatController::class, 'participants'])->name('chat.participants.index');
    Route::patch('/chat/{conversationId}/group', [ChatController::class, 'updateGroup'])->name('chat.groups.update');
    Route::post('/chat/{conversationId}/group/avatar', [ChatController::class, 'updateGroupAvatar'])->name('chat.groups.avatar');
    Route::post('/chat/{conversationId}/members', [ChatController::class, 'addMembers'])->name('chat.members.store');
    Route::delete('/chat/{conversationId}/members/me', [ChatController::class, 'leaveGroup'])->name('chat.members.leave');
    Route::delete('/chat/{conversationId}/members/{userId}', [ChatController::class, 'removeMember'])->name('chat.members.destroy');
    Route::post('/chat/{conversationId}/members/{userId}/admin', [ChatController::class, 'promoteMember'])->name('chat.members.promote');
    Route::delete('/chat/{conversationId}/members/{userId}/admin', [ChatController::class, 'demoteMember'])->name('chat.members.demote');
    Route::post('/chat/{conversationId}/invites', [ChatController::class, 'createInvite'])->name('chat.invites.store');
    Route::delete('/chat/{conversationId}/invites/{inviteId}', [ChatController::class, 'revokeInvite'])->name('chat.invites.destroy');
    Route::delete('/chat/{conversationId}/group', [ChatController::class, 'destroyGroup'])->name('chat.groups.destroy');
    Route::delete('/chat/{conversationId}', [ChatController::class, 'destroyConversation'])->name('chat.destroy');
    Route::get('/chat/{conversationId}/messages', [ChatController::class, 'messages'])->name('chat.messages.index');
    Route::post('/chat/{conversationId}/messages', [ChatController::class, 'store'])->name('chat.messages.store');
    Route::patch('/chat/{conversationId}/messages/{messageId}', [ChatController::class, 'update'])->name('chat.messages.update');
    Route::delete('/chat/{conversationId}/messages/{messageId}', [ChatController::class, 'destroy'])->name('chat.messages.destroy');
    Route::get('/chat/{conversationId}/messages/{messageId}/edits', [ChatController::class, 'messageEdits'])->name('chat.messages.edits');
    Route::get('/chat/{conversationId}/pins', [ChatController::class, 'pins'])->name('chat.pins.index');
    Route::post('/chat/{conversationId}/messages/{messageId}/pin', [ChatController::class, 'pinMessage'])->name('chat.messages.pin');
    Route::delete('/chat/{conversationId}/messages/{messageId}/pin', [ChatController::class, 'unpinMessage'])->name('chat.messages.unpin');
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
