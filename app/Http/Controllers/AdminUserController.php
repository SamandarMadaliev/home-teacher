<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admins: see who has an account and who invited them; grant or remove admin. */
class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('profile.users', [
            'activeTab' => 'users',
            'users' => User::query()->with('invitedBy:id,name')->withCount('courses')->orderBy('id')->get(),
        ]);
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        $makeAdmin = ! $user->isAdmin();

        if (! $makeAdmin && User::query()->where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['users' => 'The site needs at least one admin.']);
        }

        $user->forceFill(['is_admin' => $makeAdmin])->save();

        return back()->with('status', $makeAdmin ? "{$user->name} is now an admin." : "{$user->name} is no longer an admin.");
    }
}
