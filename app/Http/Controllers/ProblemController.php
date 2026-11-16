<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProblemRequest;
use App\Models\Problem;
use App\Models\ProblemShare;
use App\Models\ProblemSubmission;
use App\Models\User;
use App\Models\VideoNote;
use App\Services\CodeRunner;
use App\Services\LanguageRuntimeProbe;
use App\Services\ProblemJudge;
use App\Support\FunctionSignature;
use App\Support\ProblemStarterCode;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProblemController extends Controller
{
    private const SORTS = ['recent', 'title', 'difficulty'];

    /** Mine = I wrote it; shared = someone shared it with me; community = public problems by others. */
    private const SCOPES = ['mine', 'shared', 'community'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = in_array($request->query('scope'), self::SCOPES, true) ? $request->query('scope') : 'mine';
        $mine = fn ($q) => $q->where('user_id', $user->id);

        $all = $this->scopeQuery($scope, $user)
            ->with('user:id,name')
            ->withCount([
                'testCases',
                'testCases as visible_cases_count' => fn ($q) => $q->where('is_hidden', false),
                'submissions' => $mine,
                'shares',
                // People who solved it (anyone's accepted submission, counted once per person).
                'submissions as solvers_count' => fn ($q) => $q->where('status', ProblemSubmission::STATUS_ACCEPTED)
                    ->select(DB::raw('count(distinct user_id)')),
            ])
            ->withExists([
                'submissions as solved' => fn ($q) => $mine($q)->where('status', ProblemSubmission::STATUS_ACCEPTED),
                'shares as is_new' => fn ($q) => $mine($q)->whereNull('seen_at'),
            ])
            ->withMax(['submissions as last_attempt_at' => $mine], 'created_at')
            ->orderByDesc('updated_at')
            ->get();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => in_array($request->query('status'), ['todo', 'attempted', 'solved'], true) ? $request->query('status') : 'all',
            'difficulty' => in_array($request->query('difficulty'), Problem::DIFFICULTIES, true) ? $request->query('difficulty') : '',
            'tag' => mb_strtolower(trim((string) $request->query('tag', ''))),
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'recent',
        ];

        $problems = $all
            ->filter(fn (Problem $p) => $filters['q'] === '' || mb_stripos($p->title, $filters['q']) !== false)
            ->filter(fn (Problem $p) => $filters['difficulty'] === '' || $p->difficulty === $filters['difficulty'])
            ->filter(fn (Problem $p) => $filters['tag'] === '' || in_array($filters['tag'], $p->tagList(), true))
            ->filter(fn (Problem $p) => match ($filters['status']) {
                'solved' => $p->solved,
                'attempted' => ! $p->solved && $p->submissions_count > 0,
                'todo' => $p->submissions_count === 0,
                default => true,
            });

        $problems = match ($filters['sort']) {
            'title' => $problems->sortBy(fn (Problem $p) => mb_strtolower($p->title)),
            'difficulty' => $problems->sortBy(fn (Problem $p) => array_search($p->difficulty, Problem::DIFFICULTIES, true)),
            default => $problems,
        };

        $stats = [];
        foreach (Problem::DIFFICULTIES as $d) {
            $ofDifficulty = $all->where('difficulty', $d);
            $stats[$d] = ['solved' => $ofDifficulty->where('solved', true)->count(), 'total' => $ofDifficulty->count()];
        }

        $continue = $all->filter(fn (Problem $p) => ! $p->solved && $p->submissions_count > 0)
            ->sortByDesc('last_attempt_at')->first();

        return view('problems.index', [
            'scope' => $scope,
            'scopeCounts' => [
                'mine' => Problem::query()->forUser($user)->count(),
                'shared' => $this->scopeQuery('shared', $user)->count(),
                'community' => $this->scopeQuery('community', $user)->count(),
            ],
            'newShared' => ProblemShare::query()->where('user_id', $user->id)->whereNull('seen_at')
                ->whereHas('problem', fn ($q) => $q->where('user_id', '!=', $user->id))->count(),
            'problems' => $problems->values(),
            'filters' => $filters,
            'filtered' => $filters['q'] !== '' || $filters['status'] !== 'all' || $filters['difficulty'] !== '' || $filters['tag'] !== '',
            'stats' => $stats,
            'totalCount' => $all->count(),
            'solvedCount' => $all->where('solved', true)->count(),
            'allTags' => $all->flatMap(fn (Problem $p) => $p->tagList())->unique()->sort()->values()->all(),
            'continue' => $continue,
        ]);
    }

    private function scopeQuery(string $scope, User $user)
    {
        return match ($scope) {
            'shared' => Problem::query()->sharedWith($user)->where('problems.user_id', '!=', $user->id),
            'community' => Problem::query()->community()->where('problems.user_id', '!=', $user->id),
            default => Problem::query()->forUser($user),
        };
    }

    /** Jump to a random problem you haven't solved yet. */
    public function random(): RedirectResponse
    {
        $problem = $this->unsolvedQuery()->inRandomOrder()->first();

        return $problem
            ? redirect()->route('problems.show', $problem)
            : redirect()->route('problems.index')->with('status', 'Nothing left to solve — every problem is solved.');
    }

    public function create(LanguageRuntimeProbe $probe): View
    {
        // New problems default to function (LeetCode-style) mode.
        return view('problems.form', $this->formData(new Problem([
            'difficulty' => 'easy',
            'io_mode' => Problem::IO_FUNCTION,
            'signature' => ['name' => 'solve', 'params' => [['name' => 'nums', 'type' => 'int[]']], 'returns' => 'int'],
            'time_limit_ms' => Problem::DEFAULT_TIME_LIMIT_MS,
            'compare_mode' => Problem::COMPARE_EXACT,
        ]), $probe));
    }

    public function store(ProblemRequest $request): RedirectResponse
    {
        $problem = DB::transaction(function () use ($request) {
            $problem = Problem::create($request->problemAttributes() + ['user_id' => $request->user()->id]);
            $problem->forceFill(['visibility' => Problem::VISIBILITY_PRIVATE, 'revision' => 1])->save();
            $this->syncTestCases($problem, $request->tests());

            return $problem;
        });

        return $this->afterSave($request, $problem, 'Problem created.');
    }

    public function show(Problem $problem, LanguageRuntimeProbe $probe): View
    {
        $this->allow('view', $problem);
        $user = auth()->user();
        $isOwner = $problem->isOwnedBy($user);

        $share = $isOwner ? null : $problem->shareFor($user);
        if ($share && $share->seen_at === null) {
            $share->forceFill(['seen_at' => now()])->save();
        }

        $problem->load(['testCases', 'user:id,name']);
        $sig = $problem->functionSignature();
        $languages = array_values(array_filter($probe->detectList(), fn ($l) => $l['available'] && (! $sig || $sig->supports($l['key']))));
        $submissions = $problem->submissionsBy($user)->limit(30)->get();

        return view('problems.show', [
            'problem' => $problem,
            'isOwner' => $isOwner,
            'share' => $share,
            // The reference solution is never put in the page; solutions.js
            // fetches it from an endpoint that checks viewReference.
            'solutionsAccess' => match (true) {
                Gate::allows('viewSolutions', $problem) => 'all',
                Gate::allows('viewOwnerSolutions', $problem) => 'owner',
                default => 'locked',
            },
            'solutionsCount' => $problem->solutionsQuery()->count(),
            'canModerate' => Gate::allows('moderate', $problem),
            'shares' => $isOwner ? $problem->shares()->with('user:id,name,email')->get() : collect(),
            'languages' => $languages,
            'samples' => $problem->testCases->where('is_hidden', false)->values(),
            'hiddenCount' => $problem->testCases->where('is_hidden', true)->count(),
            'solved' => $problem->isSolvedBy($user),
            'submissions' => $submissions,
            'attempts' => $problem->submissionsBy($user)->count(),
            'signature' => $sig,
            // Function problems: starter code is generated client-side from the
            // signature, so only the author's overrides are sent.
            'starter' => $sig
                ? array_filter($problem->starter_code ?? [], fn ($c) => is_string($c) && trim($c) !== '')
                : ProblemStarterCode::resolve($problem->starter_code),
            'next' => $this->nextUnsolved($problem),
        ]);
    }

    public function edit(Problem $problem, LanguageRuntimeProbe $probe): View
    {
        $this->allow('manage', $problem);

        return view('problems.form', $this->formData($problem->load('testCases'), $probe) + [
            // Other people who have submitted — editing tests affects them.
            'otherSolvers' => $problem->submissions()->where('user_id', '!=', $problem->user_id)
                ->distinct()->count('user_id'),
        ]);
    }

    public function update(ProblemRequest $request, Problem $problem): RedirectResponse
    {
        $this->allow('manage', $problem);

        DB::transaction(function () use ($request, $problem) {
            $before = $problem->judgingFingerprint();
            $problem->update($request->problemAttributes());
            $this->syncTestCases($problem, $request->tests());

            // Tests, signature, comparison or limits changed: older solutions
            // were judged against something else.
            if ($problem->refresh()->judgingFingerprint() !== $before) {
                $problem->forceFill(['revision' => $problem->revision + 1])->save();
            }
        });

        return $this->afterSave($request, $problem, 'Problem updated.');
    }

    /** "Save & keep editing" stays on the form; the default goes to the solve page. */
    private function afterSave(Request $request, Problem $problem, string $message): RedirectResponse
    {
        return redirect()
            ->route($request->boolean('continue') ? 'problems.edit' : 'problems.show', $problem)
            ->with('status', $message);
    }

    public function destroy(Problem $problem): RedirectResponse
    {
        $this->allow('manage', $problem);
        $problem->delete();

        return redirect()->route('problems.index')->with('status', 'Problem deleted.');
    }

    /** Markdown preview for the statement editor. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:65535']]);

        return response()->json(['html' => VideoNote::markdownToHtml((string) ($data['body'] ?? ''))]);
    }

    /** Run against custom stdin, no verdict. */
    public function run(Request $request, Problem $problem, ProblemJudge $judge, LanguageRuntimeProbe $probe): JsonResponse
    {
        $this->allow('view', $problem);
        $data = $this->validateSolution($request);

        if (! $probe->isAvailable($data['language'])) {
            return $this->unavailable();
        }

        $sig = $problem->functionSignature();
        if ($sig && ($error = $sig->decodeArgs($data['stdin'] ?? null)[1])) {
            return response()->json(['error' => 'invalid_input', 'message' => $error], 422);
        }

        if (! $lock = $this->judgeLock()) {
            return $this->busy();
        }

        try {
            return response()->json($judge->runCustom($data['language'], $data['code'], $data['stdin'] ?? null, $sig));
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['error' => 'run_failed', 'message' => $e->getMessage()], 422);
        } finally {
            $lock->release();
        }
    }

    /** Stream a run over the visible examples only. Nothing is recorded. */
    public function runExamples(Request $request, Problem $problem, ProblemJudge $judge, LanguageRuntimeProbe $probe): JsonResponse|StreamedResponse
    {
        $this->allow('view', $problem);
        $data = $this->validateSolution($request);

        if (! $probe->isAvailable($data['language'])) {
            return $this->unavailable();
        }

        $problem->load('testCases');
        if ($problem->testCases->where('is_hidden', false)->isEmpty()) {
            return response()->json(['error' => 'no_examples', 'message' => 'This problem has no visible examples.'], 422);
        }

        if (! $lock = $this->judgeLock()) {
            return $this->busy();
        }

        return $this->ndjson(fn () => $this->judgeEvents($problem, $data, $judge, record: false), $lock);
    }

    /** Judge against every test case, streaming each result, and record the submission. */
    public function submit(Request $request, Problem $problem, ProblemJudge $judge, LanguageRuntimeProbe $probe): JsonResponse|StreamedResponse
    {
        $this->allow('view', $problem);
        $data = $this->validateSolution($request);

        if (! $probe->isAvailable($data['language'])) {
            return $this->unavailable();
        }

        $problem->load('testCases');
        if ($problem->testCases->isEmpty()) {
            return response()->json(['error' => 'no_tests', 'message' => 'This problem has no test cases.'], 422);
        }

        if (! $lock = $this->judgeLock()) {
            return $this->busy();
        }

        return $this->ndjson(fn () => $this->judgeEvents($problem, $data, $judge, record: true), $lock);
    }

    /** One of your own submissions, with its code. Other people's code is only reachable as solutions. */
    public function submission(Problem $problem, ProblemSubmission $submission): JsonResponse
    {
        $this->allow('view', $problem);
        abort_unless($submission->problem_id === $problem->id && $submission->user_id === auth()->id(), 404);

        return response()->json([
            'id' => $submission->id,
            'language' => $submission->language,
            'code' => $submission->code,
            'status' => $submission->status,
            'passed' => $submission->passed_count,
            'total' => $submission->total_count,
            'duration_ms' => $submission->duration_ms,
            'created_at' => $submission->created_at->toIso8601String(),
            'cases' => $submission->results ?? [],
            'hidden' => $submission->hidden_at !== null,
            'outdated' => $submission->problem_revision < $problem->revision,
        ]);
    }

    /**
     * Author tooling: run a reference solution over the (unsaved) test cases
     * from the form, streaming per-case results.
     */
    public function referenceCheck(Request $request, ProblemJudge $judge, LanguageRuntimeProbe $probe): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(array_keys(LanguageRuntimeProbe::LANGUAGES))],
            'code' => ['required', 'string', 'max:'.CodeRunner::MAX_CODE_BYTES],
            'compare_mode' => ['nullable', Rule::in(Problem::COMPARE_MODES)],
            'time_limit_ms' => ['nullable', 'integer', 'between:'.Problem::MIN_TIME_LIMIT_MS.','.Problem::MAX_TIME_LIMIT_MS],
            'tests' => ['required', 'array', 'min:1', 'max:50'],
            'tests.*.input' => ['nullable', 'string', 'max:'.CodeRunner::MAX_STDIN_BYTES],
            'tests.*.expected_output' => ['nullable', 'string', 'max:'.CodeRunner::MAX_OUTPUT_BYTES],
            'io_mode' => ['nullable', Rule::in(Problem::IO_MODES)],
            'signature' => ['nullable', 'array'],
        ]);

        if (! $probe->isAvailable($data['language'])) {
            return $this->unavailable();
        }

        $sig = null;
        if (($data['io_mode'] ?? null) === Problem::IO_FUNCTION) {
            $sig = FunctionSignature::fromArray($data['signature'] ?? null);
            if ($sig === null) {
                return response()->json(['error' => 'invalid_signature', 'message' => 'Finish the function signature first (name, parameters, return type).'], 422);
            }
            if (! $sig->supports($data['language'])) {
                return response()->json(['error' => 'unsupported', 'message' => 'This function can’t be solved in that language.'], 422);
            }
        }

        return $this->ndjson(function () use ($data, $judge, $sig) {
            yield ['type' => 'start', 'total' => count($data['tests'])];
            foreach ($judge->checkReference(
                $data['language'],
                $data['code'],
                array_values($data['tests']),
                $data['compare_mode'] ?? ($sig ? Problem::COMPARE_EXACT : Problem::COMPARE_TRIM_LINES),
                (int) ($data['time_limit_ms'] ?? Problem::DEFAULT_TIME_LIMIT_MS),
                $sig,
            ) as $row) {
                yield ['type' => 'case'] + $row;
            }
            yield ['type' => 'done'];
        });
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function judgeEvents(Problem $problem, array $data, ProblemJudge $judge, bool $record): \Generator
    {
        $shown = $problem->testCases->values()
            ->map(fn ($c, $i) => ['number' => $i + 1, 'hidden' => $c->is_hidden, 'label' => $c->label])
            ->when(! $record, fn ($c) => $c->reject(fn ($row) => $row['hidden']))
            ->values()->all();

        yield ['type' => 'start', 'total' => count($shown), 'cases' => $shown];

        $user = auth()->user();
        $wasSolved = $record && $problem->isSolvedBy($user);
        $cases = $judge->cases($problem, $data['language'], $data['code'], visibleOnly: ! $record);
        $rows = [];
        foreach ($cases as $row) {
            if (isset($row['type'])) { // e.g. the Go "compile" event
                yield $row;

                continue;
            }
            $rows[] = $row;
            yield ['type' => 'case'] + $row;
        }
        $verdict = $cases->getReturn();

        $done = ['type' => 'done'] + $verdict + ['recorded' => $record];

        if ($record) {
            $submission = $problem->submissions()->create([
                'user_id' => $user->id,
                'problem_revision' => $problem->revision,
                'language' => $data['language'],
                'code' => $data['code'],
                'status' => $verdict['status'],
                'passed_count' => $verdict['passed'],
                'total_count' => $verdict['total'],
                'duration_ms' => $verdict['duration_ms'],
                'results' => $rows,
            ]);

            $done['first_solve'] = ! $wasSolved && $verdict['status'] === ProblemSubmission::STATUS_ACCEPTED;
            $done['submission'] = [
                'id' => $submission->id,
                'language' => $submission->language,
                'status' => $submission->status,
                'passed_count' => $submission->passed_count,
                'total_count' => $submission->total_count,
                'duration_ms' => $submission->duration_ms,
                'created_at' => $submission->created_at->toIso8601String(),
                'attempt' => $problem->submissionsBy($user)->count(),
            ];
        }

        yield $done;
    }

    /**
     * Stream newline-delimited JSON events so the UI can show each test as it
     * finishes. `X-Accel-Buffering: no` keeps nginx from holding them back.
     *
     * @param  \Closure():\Generator<int, array<string, mixed>>  $events
     */
    private function ndjson(\Closure $events, ?Lock $lock = null): StreamedResponse
    {
        return response()->stream(function () use ($events, $lock) {
            // A cancelled request ends the script at the next write; release then too.
            if ($lock) {
                register_shutdown_function(fn () => $lock->release());
            }

            $emit = function (array $event): void {
                echo json_encode($event, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES)."\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                foreach ($events() as $event) {
                    $emit($event);
                }
            } catch (\RuntimeException|\InvalidArgumentException $e) {
                $emit(['type' => 'error', 'message' => $e->getMessage()]);
            } finally {
                $lock?->release();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** Denied looks the same as missing, so private problems can't be probed. */
    private function allow(string $ability, Problem $problem): void
    {
        abort_unless(Gate::allows($ability, $problem), 404);
    }

    /**
     * One run at a time per person: shared and public problems let other
     * people run code on this machine, so a person can't stack up runs.
     */
    private function judgeLock(): ?Lock
    {
        $lock = Cache::lock('problem-judge:'.auth()->id(), 600);

        return $lock->get() ? $lock : null;
    }

    private function busy(): JsonResponse
    {
        return response()->json([
            'error' => 'busy',
            'message' => 'Another run of yours is still going — wait for it to finish first.',
        ], 429);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => 'runtime_unavailable',
            'message' => 'That runtime is not installed on this machine.',
        ], 422);
    }

    private function validateSolution(Request $request): array
    {
        return $request->validate([
            'language' => ['required', 'string', Rule::in(array_keys(LanguageRuntimeProbe::LANGUAGES))],
            'code' => ['required', 'string', 'max:'.CodeRunner::MAX_CODE_BYTES],
            'stdin' => ['nullable', 'string', 'max:'.CodeRunner::MAX_STDIN_BYTES],
        ]);
    }

    /** Unsolved problems in my list (my own + shared with me), for Random / Next. */
    private function unsolvedQuery()
    {
        $user = auth()->user();

        return Problem::query()->inLibraryOf($user)
            ->whereDoesntHave('submissions', fn ($q) => $q->where('user_id', $user->id)->where('status', ProblemSubmission::STATUS_ACCEPTED));
    }

    /** The next unsolved problem after this one (wrapping around), or null. */
    private function nextUnsolved(Problem $problem): ?Problem
    {
        $unsolved = $this->unsolvedQuery()->whereKeyNot($problem->id)->orderBy('id')->get(['id', 'title']);

        return $unsolved->firstWhere(fn ($p) => $p->id > $problem->id) ?? $unsolved->first();
    }

    /** @return array<string, mixed> */
    private function formData(Problem $problem, LanguageRuntimeProbe $probe): array
    {
        return [
            'problem' => $problem,
            'languages' => array_values(array_filter($probe->detectList(), fn ($l) => $l['available'])),
            'allLanguages' => collect(LanguageRuntimeProbe::LANGUAGES)->map(fn ($l) => $l['label'])->all(),
            'starterDefaults' => ProblemStarterCode::TEMPLATES,
            'functionLanguages' => FunctionSignature::LANGUAGES,
            'types' => FunctionSignature::TYPES,
            'reserved' => FunctionSignature::RESERVED,
        ];
    }

    /**
     * Update test cases in place (matching by id so identities survive edits),
     * create new ones, and delete the ones that were removed.
     *
     * @param  array<int, array<string, mixed>>  $tests
     */
    private function syncTestCases(Problem $problem, array $tests): void
    {
        $existing = $problem->testCases()->get()->keyBy('id');
        $keep = [];

        foreach ($tests as $i => $t) {
            $attrs = [
                'label' => ($t['label'] ?? null) ?: null,
                'input' => $t['input'] ?? null,
                'expected_output' => $t['expected_output'],
                'is_hidden' => (bool) ($t['is_hidden'] ?? false),
                'sort_order' => $i,
            ];

            $id = (int) ($t['id'] ?? 0);
            if ($id && $existing->has($id) && ! in_array($id, $keep, true)) {
                $existing[$id]->update($attrs);
                $keep[] = $id;
            } else {
                $keep[] = $problem->testCases()->create($attrs)->id;
            }
        }

        $problem->testCases()->whereNotIn('id', $keep)->delete();
    }
}
