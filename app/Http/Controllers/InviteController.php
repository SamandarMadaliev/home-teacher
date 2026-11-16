<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Support\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InviteController extends Controller
{
    /**
     * An invite link was opened: remember it for this visitor's session and
     * send them to sign up (works for the form and for Google sign-in).
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('home')->with('status', 'You’re already signed in — that invite is for creating a new account.');
        }

        $invite = Invite::query()->where('token', $token)->first();
        if (! $invite?->isUsable()) {
            return redirect()->route('login')->withErrors([
                'email' => 'That invite link has expired or was already used. Ask the person who sent it for a new one.',
            ]);
        }

        $request->session()->put(Registration::SESSION_KEY, $invite->token);

        return redirect()->route('register');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $invites = Invite::query()
            ->with('creator:id,name')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('id')
            ->limit(100)
            ->get();

        return view('profile.invites', [
            'activeTab' => 'invites',
            'invites' => $invites,
            'mode' => Registration::mode(),
            'isAdmin' => $user->isAdmin(),
            'activeCount' => $user->invites()->active()->count(),
            'limits' => config('registration.invites'),
            'invitees' => $user->isAdmin()
                ? null
                : \App\Models\User::query()->where('invited_by_id', $user->id)->latest('id')->get(['id', 'name', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $limits = config('registration.invites');
        $admin = $user->isAdmin();

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:120'],
            'max_uses' => ['required', $admin ? Rule::in(['1', '5', '10', '25', 'unlimited']) : Rule::in(['1', '5', '10'])],
            'expires_in_days' => ['required', $admin ? Rule::in(['1', '7', '30', 'never']) : Rule::in(['1', '7', '30'])],
        ]);

        if (! $admin && $user->invites()->active()->count() >= $limits['max_active_per_user']) {
            return back()->withErrors(['max_uses' => "You can have at most {$limits['max_active_per_user']} active invites. Revoke one first."]);
        }

        $maxUses = $data['max_uses'] === 'unlimited' ? null : min((int) $data['max_uses'], $admin ? PHP_INT_MAX : $limits['max_uses']);
        $days = $data['expires_in_days'] === 'never' ? null : min((int) $data['expires_in_days'], $admin ? PHP_INT_MAX : $limits['max_days']);

        $invite = $user->invites()->create([
            'token' => Invite::newToken(),
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'max_uses' => $maxUses,
            'expires_at' => $days === null ? null : now()->addDays($days),
        ]);

        return redirect()->route('invites.index')->with('new_invite_id', $invite->id)->with('status', 'Invite link created — copy it and send it.');
    }

    public function destroy(Request $request, Invite $invite): RedirectResponse
    {
        abort_unless($invite->user_id === $request->user()->id || $request->user()->isAdmin(), 404);

        if ($invite->revoked_at === null) {
            $invite->forceFill(['revoked_at' => now()])->save();
        }

        return back()->with('status', 'Invite revoked — the link no longer works.');
    }
}
