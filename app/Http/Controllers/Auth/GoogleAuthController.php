<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LegacyLibraryClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse|RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign-in was cancelled or failed. Please try again.',
            ]);
        }

        if ($googleUser->getEmail() === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your Google account did not provide an email address.',
            ]);
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if ($user === null) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();

            if ($user !== null) {
                if ($user->google_id !== null && $user->google_id !== $googleUser->getId()) {
                    return redirect()->route('login')->withErrors([
                        'email' => 'That email is already linked to a different Google account.',
                    ]);
                }

                $user->update(['google_id' => $googleUser->getId()]);
            } else {
                $isFirstUser = User::query()->doesntExist();

                $user = User::create([
                    'name' => $googleUser->getName() ?? explode('@', $googleUser->getEmail(), 2)[0],
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => null,
                    'email_verified_at' => now(),
                ]);

                if ($isFirstUser) {
                    LegacyLibraryClaim::claimForFirstUser($user);
                }
            }
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('home'))->with('status', 'Signed in with Google.');
    }
}
