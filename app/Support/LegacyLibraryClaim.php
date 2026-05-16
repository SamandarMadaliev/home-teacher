<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Roadmap;
use App\Models\User;

class LegacyLibraryClaim
{
    /**
     * Assign orphan courses/roadmaps to the first user on a fresh install.
     */
    public static function claimForFirstUser(User $user): void
    {
        if (User::query()->whereKeyNot($user->id)->exists()) {
            return;
        }

        Course::query()->whereNull('user_id')->update(['user_id' => $user->id]);
        Roadmap::query()->whereNull('user_id')->update(['user_id' => $user->id]);
    }
}
