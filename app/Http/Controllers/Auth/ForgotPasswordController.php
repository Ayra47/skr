<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('pages.auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['login' => 'required|string']);

        $user = User::where('login', $request->login)->first();

        // Always return success to prevent user enumeration
        if ($user && $user->email && $user->email_verified_at) {
            $resetUrl = URL::temporarySignedRoute(
                'password.reset',
                now()->addHour(),
                ['user' => $user->id],
            );
            Notification::route('mail', $user->email)->notify(new PasswordResetNotification($resetUrl));
        }

        return back()->with('sent', true)->with('login', $request->login);
    }

    public function showReset(Request $request): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('forgot-password')->with('error', 'Ссылка недействительна или истекла');
        }

        $user = User::find($request->query('user'));

        if (! $user) {
            return redirect()->route('forgot-password')->with('error', 'Ссылка недействительна');
        }

        return view('pages.auth.reset-password', ['signedUrl' => $request->fullUrl()]);
    }

    public function reset(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('forgot-password')->with('error', 'Ссылка недействительна или истекла');
        }

        $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = User::find($request->query('user'));

        if (! $user) {
            return redirect()->route('forgot-password')->with('error', 'Ссылка недействительна');
        }

        $user->update([
            'password' => $request->password,
            'pending_password_hash' => null,
        ]);

        return redirect()->route('login')->with('status', 'Пароль успешно изменён — войдите с новым паролем');
    }
}
