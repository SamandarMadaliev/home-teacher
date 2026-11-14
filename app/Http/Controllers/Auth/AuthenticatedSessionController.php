<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        // Limit guesses per account (the IP alone can be spoofed through
        // X-Forwarded-For) and per account+IP.
        $account = 'login:'.Str::transliterate(Str::lower($credentials['email']));
        $keys = [$account => 10, $account.'|'.$request->ip() => 5];
        foreach ($keys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return back()->withInput($request->only('email'))->withErrors([
                    'email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
                ]);
            }
        }

        if (! Auth::attempt($credentials, $remember)) {
            foreach (array_keys($keys) as $key) {
                RateLimiter::hit($key, 60);
            }

            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        foreach (array_keys($keys) as $key) {
            RateLimiter::clear($key);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Signed out.');
    }
}
