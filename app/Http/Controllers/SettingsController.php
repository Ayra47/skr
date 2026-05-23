<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\ProfileSetting;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserKey;
use App\Notifications\DetachEmailVerification;
use App\Notifications\EmailChangeVerification;
use App\Notifications\PasswordChangeVerification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Jenssegers\Agent\Agent;

class SettingsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $currentSessionId = session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($s) use ($currentSessionId) {
                $parsed = $this->parseUserAgent($s->user_agent ?? '');

                $lastActivity = Carbon::createFromTimestamp($s->last_activity);

                return (object) [
                    'id' => $s->id,
                    'is_current' => $s->id === $currentSessionId,
                    'is_online' => $lastActivity->gt(now()->subMinutes(2)),
                    'ip_address' => $s->ip_address,
                    'browser' => $parsed['browser'],
                    'platform' => $parsed['platform'],
                    'device_type' => $parsed['device_type'],
                    'last_activity' => $lastActivity,
                ];
            });

        $loginHistory = LoginHistory::where('user_id', $user->id)
            ->latest()
            ->limit(30)
            ->get()
            ->map(function ($h) {
                $parsed = $this->parseUserAgent($h->user_agent ?? '');

                return (object) [
                    'event' => $h->event,
                    'ip_address' => $h->ip_address,
                    'browser' => $parsed['browser'],
                    'platform' => $parsed['platform'],
                    'created_at' => $h->created_at,
                ];
            });

        return view('pages.settings.index', [
            'user' => $user,
            'profileSettings' => $user->profileSetting()->firstOrNew(),
            'hasBackupCode' => filled($user->backup_code_hash),
            'sessions' => $sessions,
            'loginHistory' => $loginHistory,
        ]);
    }

    /**
     * @return array{browser: string, platform: string, device_type: string}
     */
    private function parseUserAgent(string $userAgent): array
    {
        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        $browser = $agent->browser();
        $browserVersion = $browser ? $agent->version($browser) : null;
        $platform = $agent->platform();
        $platformVersion = $platform ? $agent->version($platform) : null;

        $browserLabel = $browser ?: 'Unknown';
        if ($browserVersion) {
            $major = explode('.', $browserVersion)[0];
            $browserLabel .= ' '.$major;
        }

        $platformLabel = $platform ?: 'Unknown';
        if ($platformVersion) {
            $platformLabel .= ' '.$platformVersion;
        }

        $deviceType = 'desktop';
        if ($agent->isPhone()) {
            $deviceType = 'mobile';
        } elseif ($agent->isTablet()) {
            $deviceType = 'tablet';
        }

        return [
            'browser' => $browserLabel,
            'platform' => $platformLabel,
            'device_type' => $deviceType,
        ];
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'login' => 'required|string|max:255|unique:users,login,'.$user->id,
            'pseudonym' => 'required|string|max:50|alpha_dash:ascii|lowercase|unique:users,pseudonym,'.$user->id,
            'email' => 'nullable|email|max:255',
            'bio' => 'nullable|string|max:255',
        ]);

        $newEmail = $validated['email'] ?? null;

        // Handle email change — save as pending and send verification
        $emailUpdates = [];
        if ($newEmail !== $user->email && $newEmail !== $user->pending_email) {
            if ($newEmail) {
                $verificationUrl = URL::temporarySignedRoute(
                    'settings.email.verify',
                    now()->addHours(24),
                    ['user' => $user->id, 'email' => $newEmail],
                );
                Notification::route('mail', $newEmail)->notify(new EmailChangeVerification($verificationUrl));
                $emailUpdates['pending_email'] = $newEmail;
            } else {
                $emailUpdates['pending_email'] = null;
            }
        }

        $user->update(array_merge([
            'login' => $validated['login'],
            'name' => $validated['login'],
            'pseudonym' => $validated['pseudonym'],
        ], $emailUpdates));

        $user->profileSetting()->updateOrCreate([], ['bio' => $validated['bio'] ?? null]);

        return response()->json(['success' => true, 'verification_sent' => filled($emailUpdates['pending_email'] ?? null)]);
    }

    public function updateProfileVisibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'show_shared_chats' => ['required', 'boolean'],
            'show_shared_groups' => ['required', 'boolean'],
            'profile_access' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'online_status_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'shared_friends_count_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'feed_posts_count_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'profile_posts_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'avatar_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'profile_communities_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'community_activity_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'community_posts_profile_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'community_posts_feed_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'joined_communities_activity_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
            'community_roles_visibility' => ['required', Rule::in(ProfileSetting::audienceValues())],
        ]);

        auth()->user()->profileSetting()->updateOrCreate([], $validated);

        return response()->json(['success' => true]);
    }

    public function resendEmailVerification(): JsonResponse
    {
        $user = auth()->user();

        if (! $user->pending_email) {
            return response()->json(['success' => false, 'message' => 'Нет ожидающего подтверждения email'], 422);
        }

        $verificationUrl = URL::temporarySignedRoute(
            'settings.email.verify',
            now()->addHours(24),
            ['user' => $user->id, 'email' => $user->pending_email],
        );

        Notification::route('mail', $user->pending_email)->notify(new EmailChangeVerification($verificationUrl));

        return response()->json(['success' => true]);
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('settings.index')->with('error', 'Ссылка недействительна или истекла');
        }

        $user = User::find($request->query('user'));

        if (! $user || $user->pending_email !== $request->query('email')) {
            return redirect()->route('settings.index')->with('error', 'Ссылка недействительна');
        }

        $user->update([
            'email' => $user->pending_email,
            'email_verified_at' => now(),
            'pending_email' => null,
        ]);

        return redirect()->route('settings.index')->with('success', 'Email подтверждён');
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ]);

        $user = auth()->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $filename = 'avatars/'.Str::uuid().'.webp';
        $encoded = (new ImageManager(new GdDriver))
            ->decode($request->file('avatar')->getRealPath())
            ->cover(128, 128)
            ->encode(new WebpEncoder(80));
        Storage::disk('public')->put($filename, (string) $encoded);

        $user->update(['avatar' => $filename]);

        return response()->json([
            'success' => true,
            'avatar_url' => '/storage/'.$filename,
        ]);
    }

    public function deleteAvatar(): JsonResponse
    {
        $user = auth()->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json(['success' => true]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Неверный текущий пароль'], 422);
        }

        if ($user->email) {
            $pendingHash = Hash::make($request->password);
            $user->update(['pending_password_hash' => $pendingHash]);

            $verificationUrl = URL::temporarySignedRoute(
                'settings.password.verify',
                now()->addHour(),
                ['user' => $user->id],
            );
            $user->notify(new PasswordChangeVerification($verificationUrl));

            return response()->json(['success' => true, 'verification_sent' => true, 'email' => $user->email]);
        }

        $user->update(['password' => $request->password]);
        $this->invalidateOtherSessions($user->id);

        return response()->json(['success' => true, 'verification_sent' => false]);
    }

    public function verifyPasswordChange(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('settings.index')->with('error', 'Ссылка недействительна или истекла');
        }

        $user = User::find($request->query('user'));

        if (! $user || ! $user->pending_password_hash) {
            return redirect()->route('settings.index')->with('error', 'Ссылка недействительна');
        }

        // Bypass the 'hashed' cast — pending_password_hash is already a bcrypt hash
        DB::table('users')->where('id', $user->id)->update([
            'password' => $user->pending_password_hash,
            'pending_password_hash' => null,
        ]);

        $this->invalidateOtherSessions($user->id);

        return redirect()->route('settings.index')->with('success', 'Пароль успешно изменён');
    }

    public function detachEmail(): JsonResponse
    {
        $user = auth()->user();

        if (! $user->email && ! $user->pending_email) {
            return response()->json(['success' => false, 'message' => 'Email не привязан'], 422);
        }

        $confirmationUrl = URL::temporarySignedRoute(
            'settings.email.detach.confirm',
            now()->addHour(),
            ['user' => $user->id],
        );

        $user->notify(new DetachEmailVerification($confirmationUrl));

        return response()->json(['success' => true]);
    }

    public function confirmDetachEmail(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('settings.index')->with('error', 'Ссылка недействительна или истекла');
        }

        $user = User::find($request->query('user'));

        if (! $user) {
            return redirect()->route('settings.index')->with('error', 'Пользователь не найден');
        }

        $user->update([
            'email' => null,
            'email_verified_at' => null,
            'pending_email' => null,
            'pending_password_hash' => null,
        ]);

        return redirect()->route('settings.index')->with('success', 'Email успешно отвязан');
    }

    public function updateTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['enabled' => 'required|boolean']);

        $user = auth()->user();

        if ($request->boolean('enabled') && (! $user->email || ! $user->email_verified_at)) {
            return response()->json(['success' => false, 'message' => 'Нужна подтверждённая почта'], 422);
        }

        $user->update(['two_factor_enabled' => $request->boolean('enabled')]);

        return response()->json(['success' => true]);
    }

    public function storeBackupCodeHash(Request $request): JsonResponse
    {
        $request->validate([
            'hash' => 'required|string|size:64',
            'public_key_jwk' => 'nullable|string|max:2048',
        ]);

        $user = auth()->user();
        $user->update(['backup_code_hash' => $request->hash]);

        if ($request->filled('public_key_jwk')) {
            UserKey::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'public_key_jwk' => $request->public_key_jwk,
                    'key_change_source' => 'settings',
                    'key_changed_at' => now(),
                ],
            );
        }

        return response()->json(['success' => true]);
    }

    public function getNotificationPrefs(): JsonResponse
    {
        $user = auth()->user();
        $key = $user->userKey;

        return response()->json([
            'notify_sound' => $key?->notify_sound ?? true,
            'notify_email' => $key?->notify_email ?? false,
            'notify_email_text' => $key?->notify_email_text ?? false,
            'notify_push' => $key?->notify_push ?? false,
            'notify_push_text' => $key?->notify_push_text ?? false,
        ]);
    }

    public function updateNotificationPrefs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notify_sound' => 'sometimes|boolean',
            'notify_email' => 'sometimes|boolean',
            'notify_email_text' => 'sometimes|boolean',
            'notify_push' => 'sometimes|boolean',
            'notify_push_text' => 'sometimes|boolean',
        ]);

        $user = auth()->user();

        if ($request->boolean('notify_email') && (! $user->email || ! $user->email_verified_at)) {
            return response()->json(['success' => false, 'message' => 'Нужна подтверждённая почта'], 422);
        }

        UserKey::updateOrCreate(
            ['user_id' => $user->id],
            $validated,
        );

        return response()->json(['success' => true]);
    }

    public function updateAccentColor(Request $request): JsonResponse
    {
        $request->validate([
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        auth()->user()->profileSetting()->updateOrCreate([], [
            'accent_color' => $request->accent_color,
        ]);

        return response()->json(['success' => true]);
    }

    public function heartbeat(): JsonResponse
    {
        auth()->user()->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true]);
    }

    private function invalidateOtherSessions(int $userId): void
    {
        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', session()->getId())
            ->delete();

        DB::table('users')
            ->where('id', $userId)
            ->update(['remember_token' => null]);

        PushSubscription::where('user_id', $userId)->delete();
    }
}
