<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TwoFactorController;
use App\Models\LoginHistory;
use App\Models\User;
use App\Notifications\LoginNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('pages.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('login', $credentials['login'])->first();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if ($user->two_factor_enabled && $user->email && ! TwoFactorController::isTrusted($request, $user->id)) {
                Auth::logout();
                $request->session()->put('2fa_user_id', $user->id);
                $request->session()->put('2fa_remember', $request->boolean('remember'));
                TwoFactorController::sendCode($user);

                return redirect()->route('2fa.show');
            }

            $request->session()->regenerate();

            LoginHistory::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'event' => 'login_success',
            ]);

            $user->notify(new LoginNotification($user->id, $request->ip(), $request->userAgent() ?? ''));

            return redirect()->intended('/');
        }

        if ($user) {
            LoginHistory::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'event' => 'login_fail',
            ]);
        }

        throw ValidationException::withMessages([
            'login' => __('auth.failed'),
        ]);
    }

    public function loginWithBackupCode(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => 'required|string',
            'backup_code' => 'required|string',
        ]);

        $user = User::where('login', $request->login)->first();

        if (! $user || ! $user->backup_code_hash) {
            return back()->withErrors(['backup_code' => 'Неверный логин или код восстановления']);
        }

        $hash = hash('sha256', $request->backup_code);

        if (! hash_equals($user->backup_code_hash, $hash)) {
            return back()->withErrors(['backup_code' => 'Неверный логин или код восстановления']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
