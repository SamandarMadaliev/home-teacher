<?php

namespace App\Policies;

use App\Models\Problem;
use App\Models\User;

/**
 * Who may do what with a problem. Controllers turn a denial into a 404 so
 * people can't tell a private problem from one that doesn't exist.
 *
 * - Owner: everything.
 * - Shared with you (by email): open, run and submit; never edit or see hidden tests.
 * - Public ("community"): every member, unless an admin has hidden it.
 */
class ProblemPolicy
{
    public function view(User $user, Problem $problem): bool
    {
        if ($problem->isOwnedBy($user) || $problem->shares()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return $problem->isPublic() && (! $problem->isModerated() || $user->isAdmin());
    }

    /** Edit, delete, share, change visibility, check a reference solution. */
    public function manage(User $user, Problem $problem): bool
    {
        return $problem->isOwnedBy($user);
    }

    /** Every person's solutions: after solving (owners and moderating admins don't have to). */
    public function viewSolutions(User $user, Problem $problem): bool
    {
        return $this->view($user, $problem) && (
            $problem->isOwnedBy($user)
            || $problem->isSolvedBy($user)
            || $this->moderate($user, $problem)
        );
    }

    /** The owner's own solutions (reference + accepted submissions), before solving. */
    public function viewOwnerSolutions(User $user, Problem $problem): bool
    {
        return $this->viewSolutions($user, $problem)
            || (bool) $problem->shareFor($user)?->include_solution;
    }

    public function viewReference(User $user, Problem $problem): bool
    {
        if ($problem->reference_solution === null || ! $this->view($user, $problem)) {
            return false;
        }

        return $problem->isOwnedBy($user)
            || (bool) $problem->shareFor($user)?->include_solution
            || ($problem->show_reference_solution && $problem->isSolvedBy($user));
    }

    /** Admins can hide community problems and solutions; they still can't open private ones. */
    public function moderate(User $user, Problem $problem): bool
    {
        return $user->isAdmin() && $problem->isPublic();
    }
}
