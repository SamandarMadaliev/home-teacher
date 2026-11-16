<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProblemRequest;
use App\Models\Problem;
use App\Models\ProblemSubmission;
use App\Models\VideoNote;
use App\Services\CodeRunner;
use App\Services\LanguageRuntimeProbe;
use App\Services\ProblemJudge;
use App\Support\FunctionSignature;
use App\Support\ProblemStarterCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProblemController extends Controller
{
    private const SORTS = ['recent', 'title', 'difficulty'];

    public function index(Request $request): View
    {
        $all = Problem::query()
            ->forCurrentUser()
            ->withCount([
                'testCases',
                'testCases as visible_cases_count' => fn ($q) => $q->where('is_hidden', false),
                'submissions',
            ])
            ->withExists(['submissions as solved' => fn ($q) => $q->where('status', ProblemSubmission::STATUS_ACCEPTED)])
            ->withMax('submissions as last_attempt_at', 'created_at')
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
            $this->syncTestCases($problem, $request->tests());

            return $problem;
        });

        return $this->afterSave($request, $problem, 'Problem created.');
    }

    public function show(Problem $problem, LanguageRuntimeProbe $probe): View
    {
        $this->authorizeOwner($problem);

        $problem->load('testCases');
        $sig = $problem->functionSignature();
        $languages = array_values(array_filter($probe->detectList(), fn ($l) => $l['available'] && (! $sig || $sig->supports($l['key']))));
        $submissions = $problem->submissions()->limit(30)->get();

        return view('problems.show', [
            'problem' => $problem,
            'languages' => $languages,
            'samples' => $problem->testCases->where('is_hidden', false)->values(),
            'hiddenCount' => $problem->testCases->where('is_hidden', true)->count(),
            'solved' => $problem->isSolved(),
            'submissions' => $submissions,
            'attempts' => $problem->submissions()->count(),
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
        $this->authorizeOwner($problem);

        return view('problems.form', $this->formData($problem->load('testCases'), $probe));
    }

    public function update(ProblemRequest $request, Problem $problem): RedirectResponse
    {
        $this->authorizeOwner($problem);

        DB::transaction(function () use ($request, $problem) {
            $problem->update($request->problemAttributes());
            $this->syncTestCases($problem, $request->tests());
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
        $this->authorizeOwner($problem);
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
        $this->authorizeOwner($problem);
        $data = $this->validateSolution($request);

        if (! $probe->isAvailable($data['language'])) {
            return $this->unavailable();
        }

        $sig = $problem->functionSignature();
        if ($sig && ($error = $sig->decodeArgs($data['stdin'] ?? null)[1])) {
            return response()->json(['error' => 'invalid_input', 'message' => $error], 422);
        }

        try {
            return response()->json($judge->runCustom($data['language'], $data['code'], $data['stdin'] ?? null, $sig));
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['error' => 'run_failed', 'message' => $e->getMessage()], 422);
        }
    }

    /** Stream a run over the visible examples only. Nothing is recorded. */
    public function runExamples(Request $request, Problem $problem, ProblemJudge $judge, LanguageRuntimeProbe $probe): JsonResponse|StreamedResponse
    {
        $this->authorizeOwner($problem);
        $data = $this->validateSolution($request);

        if (! $probe->isAvailable($data['language'])) {
            return $this->unavailable();
        }

        $problem->load('testCases');
        if ($problem->testCases->where('is_hidden', false)->isEmpty()) {
            return response()->json(['error' => 'no_examples', 'message' => 'This problem has no visible examples.'], 422);
        }

        return $this->ndjson(fn () => $this->judgeEvents($problem, $data, $judge, record: false));
    }

    /** Judge against every test case, streaming each result, and record the submission. */
    public function submit(Request $request, Problem $problem, ProblemJudge $judge, LanguageRuntimeProbe $probe): JsonResponse|StreamedResponse
    {
        $this->authorizeOwner($problem);
        $data = $this->validateSolution($request);

        if (! $probe->isAvailable($data['language'])) {
            return $this->unavailable();
        }

        $problem->load('testCases');
        if ($problem->testCases->isEmpty()) {
            return response()->json(['error' => 'no_tests', 'message' => 'This problem has no test cases.'], 422);
        }

        return $this->ndjson(fn () => $this->judgeEvents($problem, $data, $judge, record: true));
    }

    public function submission(Problem $problem, ProblemSubmission $submission): JsonResponse
    {
        $this->authorizeOwner($problem);
        abort_unless($submission->problem_id === $problem->id, 404);

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

        $wasSolved = $record && $problem->isSolved();
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
                'user_id' => auth()->id(),
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
                'attempt' => $problem->submissions()->count(),
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
    private function ndjson(\Closure $events): StreamedResponse
    {
        return response()->stream(function () use ($events) {
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
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function authorizeOwner(Problem $problem): void
    {
        abort_unless($problem->user_id === auth()->id(), 404);
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

    private function unsolvedQuery()
    {
        return Problem::query()->forCurrentUser()
            ->whereDoesntHave('submissions', fn ($q) => $q->where('status', ProblemSubmission::STATUS_ACCEPTED));
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
