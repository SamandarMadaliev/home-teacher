<?php

namespace App\Http\Controllers;

use App\Models\Problem;
use App\Models\ProblemShare;
use App\Models\ProblemSubmission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Owner-only sharing: give specific people access by email (at most
 * {@see Problem::MAX_SHARES} while private), or make the problem public.
 * Admins can hide a public problem from the community (moderation).
 */
class ProblemShareController extends Controller
{
    public function store(Request $request, Problem $problem): JsonResponse
    {
        $this->allow('manage', $problem);
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'include_solution' => ['nullable', 'boolean'],
            'make_public' => ['nullable', 'boolean'],
        ]);

        $recipient = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($data['email']))])->first();
        if ($recipient === null) {
            return $this->error('not_registered', 'This person needs to register first. Once they have an account, share the problem again.');
        }
        if ($recipient->id === $problem->user_id) {
            return $this->error('self', 'That’s you — you already own this problem.');
        }

        return DB::transaction(function () use ($problem, $recipient, $data) {
            // Lock the problem row so two quick adds can't both slip under the limit.
            $problem = Problem::query()->lockForUpdate()->findOrFail($problem->id);

            if ($problem->shares()->where('user_id', $recipient->id)->exists()) {
                return $this->error('already_shared', "{$recipient->name} already has access.");
            }

            if (! $problem->isPublic() && $problem->shares()->count() >= Problem::MAX_SHARES) {
                if (! ($data['make_public'] ?? false)) {
                    return $this->error('limit', 'A problem can be shared with at most '.Problem::MAX_SHARES.' people. Sharing with more makes it a community problem that every member can see and solve.', 409);
                }
                if ($problem->isModerated()) {
                    return $this->error('moderated', 'An admin has hidden this problem from the community, so it can’t be made public.');
                }
                $this->publish($problem);
            }

            $problem->shares()->create([
                'user_id' => $recipient->id,
                'include_solution' => (bool) ($data['include_solution'] ?? false),
            ]);

            return $this->state($problem, "Shared with {$recipient->name}.");
        });
    }

    public function update(Request $request, Problem $problem, ProblemShare $share): JsonResponse
    {
        $this->allow('manage', $problem);
        abort_unless($share->problem_id === $problem->id, 404);
        $data = $request->validate(['include_solution' => ['required', 'boolean']]);

        $share->update(['include_solution' => $data['include_solution']]);

        return $this->state($problem, 'Saved.');
    }

    public function destroy(Problem $problem, ProblemShare $share): JsonResponse
    {
        $this->allow('manage', $problem);
        abort_unless($share->problem_id === $problem->id, 404);

        $share->delete();

        return $this->state($problem, 'Access removed. Their submissions are kept and come back if you share again.');
    }

    /** private | public. "Only me" also removes every share (clear_shares). */
    public function visibility(Request $request, Problem $problem): JsonResponse
    {
        $this->allow('manage', $problem);
        $data = $request->validate([
            'visibility' => ['required', Rule::in(Problem::VISIBILITIES)],
            'clear_shares' => ['nullable', 'boolean'],
        ]);

        if ($data['visibility'] === Problem::VISIBILITY_PUBLIC) {
            if ($problem->isModerated()) {
                return $this->error('moderated', 'An admin has hidden this problem from the community, so it can’t be made public.');
            }
            $this->publish($problem);

            return $this->state($problem, 'This is now a community problem.');
        }

        if ($data['clear_shares'] ?? false) {
            $problem->shares()->delete();
        } elseif ($problem->shares()->count() > Problem::MAX_SHARES) {
            return $this->error('too_many', 'Remove people until '.Problem::MAX_SHARES.' or fewer are left, or choose “Only me”.');
        }

        $problem->forceFill(['visibility' => Problem::VISIBILITY_PRIVATE])->save();

        return $this->state($problem, ($data['clear_shares'] ?? false) ? 'Only you can see this problem now.' : 'Only the people you shared with can see it now.');
    }

    /** Admin: hide a public problem from everyone but its owner and the people it's shared with (or lift that). */
    public function moderate(Request $request, Problem $problem): JsonResponse
    {
        abort_unless(Gate::allows('moderate', $problem), 404);
        $hide = $request->validate(['hidden' => ['required', 'boolean']])['hidden'];

        $problem->forceFill([
            'moderated_at' => $hide ? now() : null,
            'moderated_by_id' => $hide ? $request->user()->id : null,
        ])->save();

        return response()->json(['message' => $hide ? 'Hidden from the community.' : 'Visible to the community again.', 'moderated' => $hide]);
    }

    private function publish(Problem $problem): void
    {
        $problem->forceFill([
            'visibility' => Problem::VISIBILITY_PUBLIC,
            'published_at' => $problem->published_at ?? now(),
        ])->save();
    }

    private function state(Problem $problem, string $message): JsonResponse
    {
        $problem->refresh();

        return response()->json([
            'message' => $message,
            'visibility' => $problem->visibility,
            'max_shares' => Problem::MAX_SHARES,
            'shares' => $problem->shares()->with('user:id,name,email')->get()->map(fn (ProblemShare $s) => [
                'id' => $s->id,
                'name' => $s->user->name,
                'email' => $s->user->email,
                'include_solution' => $s->include_solution,
            ])->values(),
            // Shown before making it public: your accepted code becomes visible to solvers.
            'own_solutions' => $problem->submissionsBy($problem->user_id)
                ->where('status', ProblemSubmission::STATUS_ACCEPTED)->whereNull('hidden_at')->count(),
        ]);
    }

    private function error(string $code, string $message, int $status = 422): JsonResponse
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }

    private function allow(string $ability, Problem $problem): void
    {
        abort_unless(Gate::allows($ability, $problem), 404);
    }
}
