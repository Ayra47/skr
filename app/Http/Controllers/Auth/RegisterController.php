<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        return view('pages.auth.register', [
            'generatedPseudonym' => $this->generatePseudonym(),
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => 'required|string|max:255|unique:users',
            'pseudonym' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('users', 'pseudonym'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $pseudonym = $validated['pseudonym'] ?: $this->generateUniquePseudonym();

        $user = User::create([
            'login' => $validated['login'],
            'name' => $validated['login'],
            'pseudonym' => $pseudonym,
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect('/');
    }

    private function generatePseudonym(): string
    {
        $firstParts = [
            'crow', 'north', 'silver', 'amber', 'lonely', 'pale', 'red',
            'iron', 'velvet', 'quiet', 'dark', 'still', 'wild', 'tall', 'soft',
        ];
        $secondParts = [
            'fox', 'wind', 'fern', 'orbit', 'echo', 'tide', 'moss', 'ash',
            'kite', 'spire', 'hawk', 'dune', 'pine', 'cliff', 'flare',
        ];

        return sprintf(
            '%s-%s-%d',
            $firstParts[array_rand($firstParts)],
            $secondParts[array_rand($secondParts)],
            random_int(100, 999),
        );
    }

    private function generateUniquePseudonym(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $this->generatePseudonym();

            if (! User::query()->where('pseudonym', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('anon-%s', bin2hex(random_bytes(4)));
    }
}
