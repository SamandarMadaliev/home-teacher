<?php

namespace App\Support;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sign-up rules. The invite a visitor opened is remembered in their session
 * (see InviteController::accept) and consumed when the account is created,
 * whether by the sign-up form or by "Continue with Google".
 */
class Registration
{
    public const SESSION_KEY = 'registration.invite_token';

    public static function mode(): string
    {
        $mode = config('registration.mode');

        return in_array($mode, ['open', 'invite', 'closed'], true) ? $mode : 'invite';
    }

    /** The site has no accounts yet: the first one is always allowed (and becomes admin). */
    public static function isFirstUser(): bool
    {
        return User::query()->doesntExist();
    }

    /** Should "Create account" links be shown to everyone? */
    public static function isOpenToPublic(): bool
    {
        return self::mode() === 'open' || self::isFirstUser();
    }

    public static function pendingInvite(Request $request): ?Invite
    {
        $token = $request->session()->get(self::SESSION_KEY);
        if (! is_string($token) || $token === '') {
            return null;
        }
        $invite = Invite::query()->with('creator')->where('token', $token)->first();

        return $invite?->isUsable() ? $invite : null;
    }

    /**
     * Can this visitor create an account right now? Returns null when yes,
     * otherwise the reason to show them.
     */
    public static function refusal(Request $request): ?string
    {
        if (self::isFirstUser() || self::mode() === 'open') {
            return null;
        }
        if (self::mode() === 'closed') {
            return 'Sign-up is closed on this site.';
        }

        return self::pendingInvite($request)
            ? null
            : 'Sign-up on this site is by invitation. Open the invite link you were sent, then create your account.';
    }

    /**
     * Create the account, consuming the invite atomically (a single-use link
     * can't be used twice by two people at once). The first account becomes
     * the admin.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws \RuntimeException when sign-up isn't allowed (any more)
     */
    public static function createUser(Request $request, array $attributes): User
    {
        return DB::transaction(function () use ($request, $attributes) {
            $first = self::isFirstUser();
            $invite = null;

            if (! $first && self::mode() !== 'open') {
                if (self::mode() === 'closed') {
                    throw new \RuntimeException('Sign-up is closed on this site.');
                }
                $token = (string) $request->session()->get(self::SESSION_KEY);
                $invite = Invite::query()->where('token', $token)->lockForUpdate()->first();
                if (! $invite?->isUsable()) {
                    throw new \RuntimeException('That invite link is no longer valid. Ask for a new one.');
                }
                $invite->increment('uses');
            }

            $user = User::create($attributes + ['invited_by_id' => $invite?->user_id]);
            if ($first) {
                $user->forceFill(['is_admin' => true])->save();
                LegacyLibraryClaim::claimForFirstUser($user);
            }
            $request->session()->forget(self::SESSION_KEY);

            return $user;
        });
    }
}
