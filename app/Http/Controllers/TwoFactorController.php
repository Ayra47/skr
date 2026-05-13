<?php

namespace App\Http\Controllers;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('pages.auth.two-factor');
    }

    public function verify(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('2fa_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate(['code' => 'required|string|size:6']);

        if (
            $user->two_factor_code !== $request->code ||
            ! $user->two_factor_code_expires_at ||
            $user->two_factor_code_expires_at->isPast()
        ) {
            return back()->withErrors(['code' => 'Неверный или устаревший код']);
        }

        $user->update(['two_factor_code' => null, 'two_factor_code_expires_at' => null]);

        Auth::login($user, $request->session()->get('2fa_remember', false));
        $request->session()->forget(['2fa_user_id', '2fa_remember']);
        $request->session()->regenerate();

        if ($request->boolean('remember_device')) {
            $this->trustDevice($request, $user->id);
        }

        return redirect()->intended('/');
    }

    public function resend(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('2fa_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $user->email) {
            return redirect()->route('login');
        }

        $this->sendCode($user);

        return back()->with('resent', true);
    }

    public static function sendCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update([
            'two_factor_code' => $code,
            'two_factor_code_expires_at' => now()->addMinutes(10),
        ]);
        Notification::route('mail', $user->email)->notify(new TwoFactorCodeNotification($code));
    }

    public static function isTrusted(Request $request, int $userId): bool
    {
        $token = $request->cookie('2fa_device');

        if (! $token) {
            return false;
        }

        return TrustedDevice::where('user_id', $userId)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function trustDevice(Request $request, int $userId): void
    {
        $token = bin2hex(random_bytes(32));

        TrustedDevice::create([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        cookie()->queue(cookie('2fa_device', $token, 60 * 24 * 7, '/', null, true, true));
    }
}
