<?php

namespace App\Http\Controllers;

use App\Models\Problem;
use App\Models\ProblemSubmission;
use App\Services\LanguageRuntimeProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Community solutions: each person's latest accepted submission per
 * language, visible to people who solved the problem. Authors can hide
 * their own; admins can hide any on a public problem.
 *
 * Only code, language, runtime and date are shown — never per-test results.
 */
class ProblemSolutionController extends Controller
{
    public function index(Request $request, Problem $problem): JsonResponse
    {
        $this->allow('view', $problem);
        $access = $this->access($problem);
        if ($access === null) {
            return response()->json([
                'error' => 'locked',
                'message' => 'Solve this problem to see how other people solved it.',
            ], 403);
        }

        $filters = $request->validate([
            'language' => ['nullable', Rule::in(array_keys(LanguageRuntimeProbe::LANGUAGES))],
            'sort' => ['nullable', Rule::in(['newest', 'fastest'])],
        ]);
        $moderator = Gate::allows('moderate', $problem);
        $me = $request->user()->id;

        $rows = $problem->solutionsQuery(includeModerated: $moderator)
            ->with('user:id,name')
            ->when($access === 'owner', fn ($q) => $q->where('user_id', $problem->user_id))
            ->when($filters['language'] ?? null, fn ($q, $lang) => $q->where('language', $lang))
            ->when(($filters['sort'] ?? 'newest') === 'fastest',
                fn ($q) => $q->orderBy('duration_ms')->orderByDesc('id'),
                fn ($q) => $q->orderByDesc('id'))
            ->limit(200)
            ->get();

        $reference = Gate::allows('viewReference', $problem)
            && (! isset($filters['language']) || $filters['language'] === $problem->reference_language)
            ? ['language' => $problem->reference_language, 'author' => $problem->user?->name]
            : null;

        return response()->json([
            'access' => $access,
            'reference' => $reference,
            'solutions' => $rows->map(fn (ProblemSubmission $s) => [
                'id' => $s->id,
                'author' => $s->user?->name ?? 'Deleted user',
                'is_you' => $s->user_id === $me,
                'is_owner' => $s->user_id === $problem->user_id,
                'language' => $s->language,
                'duration_ms' => $s->duration_ms,
                'created_at' => $s->created_at->toIso8601String(),
                'outdated' => $s->problem_revision < $problem->revision,
                'moderated' => $s->moderated_at !== null,
            ])->values(),
        ]);
    }

    public function show(Request $request, Problem $problem, ProblemSubmission $submission): JsonResponse
    {
        $this->allow('view', $problem);
        $access = $this->access($problem);
        $visible = $access !== null
            && $submission->problem_id === $problem->id
            && ($access === 'all' || $submission->user_id === $problem->user_id)
            && $problem->solutionsQuery(includeModerated: Gate::allows('moderate', $problem))->whereKey($submission->id)->exists();
        abort_unless($visible, 404);

        return response()->json([
            'id' => $submission->id,
            'author' => $submission->user?->name ?? 'Deleted user',
            'language' => $submission->language,
            'code' => $submission->code,
            'duration_ms' => $submission->duration_ms,
            'created_at' => $submission->created_at->toIso8601String(),
            'outdated' => $submission->problem_revision < $problem->revision,
            'moderated' => $submission->moderated_at !== null,
        ]);
    }

    /** The author's reference solution, when they allow it. */
    public function reference(Problem $problem): JsonResponse
    {
        abort_unless(Gate::allows('viewReference', $problem), 404);

        return response()->json([
            'author' => $problem->user?->name,
            'language' => $problem->reference_language,
            'code' => $problem->reference_solution,
        ]);
    }

    /** Hide or show one of your own accepted submissions in Solutions. */
    public function hide(Request $request, Problem $problem, ProblemSubmission $submission): JsonResponse
    {
        $this->allow('view', $problem);
        abort_unless($submission->problem_id === $problem->id && $submission->user_id === $request->user()->id, 404);
        $hidden = $request->validate(['hidden' => ['required', 'boolean']])['hidden'];

        $submission->forceFill(['hidden_at' => $hidden ? now() : null])->save();

        return response()->json(['hidden' => $hidden, 'message' => $hidden ? 'Hidden from Solutions.' : 'Shown in Solutions.']);
    }

    /** Hide every accepted submission of yours on this problem. */
    public function hideAll(Request $request, Problem $problem): JsonResponse
    {
        $this->allow('view', $problem);
        $count = $problem->submissionsBy($request->user())
            ->where('status', ProblemSubmission::STATUS_ACCEPTED)
            ->whereNull('hidden_at')
            ->update(['hidden_at' => now()]);

        return response()->json(['hidden' => $count, 'message' => "Hid {$count} ".str('solution')->plural($count).'.']);
    }

    /** Admin: hide (or restore) someone's solution on a public problem. */
    public function moderate(Request $request, Problem $problem, ProblemSubmission $submission): JsonResponse
    {
        abort_unless(Gate::allows('moderate', $problem) && $submission->problem_id === $problem->id, 404);
        $hide = $request->validate(['hidden' => ['required', 'boolean']])['hidden'];

        $submission->forceFill([
            'moderated_at' => $hide ? now() : null,
            'moderated_by_id' => $hide ? $request->user()->id : null,
        ])->save();

        return response()->json(['moderated' => $hide, 'message' => $hide ? 'Solution hidden by an admin.' : 'Solution restored.']);
    }

    /** 'all' (every solution), 'owner' (only the owner's, via an include-solution share) or null. */
    private function access(Problem $problem): ?string
    {
        return match (true) {
            Gate::allows('viewSolutions', $problem) => 'all',
            Gate::allows('viewOwnerSolutions', $problem) => 'owner',
            default => null,
        };
    }

    private function allow(string $ability, Problem $problem): void
    {
        abort_unless(Gate::allows($ability, $problem), 404);
    }
}
