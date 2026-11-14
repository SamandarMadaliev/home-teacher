<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', [
            'refusal' => Registration::refusal($request),
            'invite' => Registration::pendingInvite($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($refusal = Registration::refusal($request)) {
            return redirect()->route('register')->withErrors(['email' => $refusal]);
        }

        try {
            $user = Registration::createUser($request, [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
        } catch (\RuntimeException $e) {
            return redirect()->route('register')->withInput($request->only('name', 'email'))->withErrors(['email' => $e->getMessage()]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('home')->with('status', 'Welcome! Your account is ready.');
    }
}
