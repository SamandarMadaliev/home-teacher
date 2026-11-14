<?php

namespace App\Services;

use App\Models\Problem;
use App\Models\ProblemSubmission;
use App\Models\ProblemTestCase;
use App\Support\FunctionSignature;

/**
 * Judges a solution by running it through {@see CodeRunner} once per test case:
 * the case's `input` goes to stdin and stdout is compared to `expected_output`
 * using the problem's compare mode (see {@see Problem::COMPARE_MODES}).
 *
 * Function-style problems ({@see Problem::isFunction()}) go through
 * {@see FunctionHarness}: the input holds the arguments as JSON lines and the
 * function's JSON return value is compared to `expected_output`.
 *
 * Cases are produced lazily by {@see cases()} so the controller can stream
 * each result to the browser as soon as it finishes.
 */
class ProblemJudge
{
    public const FLOAT_TOLERANCE = 1e-6;

    public function __construct(private CodeRunner $runner, private FunctionHarness $harness) {}

    /**
     * Judge the problem's test cases one at a time.
     *
     * Yields one presented row per case (hidden cases carry no input/output —
     * and no stderr, which could otherwise be used to print the input back).
     * The generator's return value is the overall verdict.
     *
     * @return \Generator<int, array<string, mixed>, mixed, array{status:string, passed:int, total:int, duration_ms:int}>
     */
    public function cases(Problem $problem, string $language, string $code, bool $visibleOnly = false): \Generator
    {
        $numbered = [];
        foreach ($problem->testCases->values() as $i => $case) {
            if ($visibleOnly && $case->is_hidden) {
                continue;
            }
            $numbered[] = [$case, $i + 1];
        }

        $passed = 0;
        $duration = 0;
        $status = ProblemSubmission::STATUS_ACCEPTED;
        $session = $this->open($problem->functionSignature(), $language, $code);

        try {
            if ($session['compile']) {
                // Compiler output never sees test data, so it's safe to show
                // even when there are hidden tests.
                yield ['type' => 'compile'] + $session['compile'];
                if (! $session['compile']['ok']) {
                    return [
                        'status' => ProblemSubmission::STATUS_COMPILE_ERROR,
                        'passed' => 0,
                        'total' => count($numbered),
                        'duration_ms' => 0,
                        'compile' => $session['compile'],
                    ];
                }
            }

            foreach ($numbered as [$case, $number]) {
                $result = $this->runCase($problem, $case, $session, $language);
                $duration += $result['duration_ms'];

                if ($result['passed']) {
                    $passed++;
                } elseif ($status === ProblemSubmission::STATUS_ACCEPTED) {
                    $status = $result['status'];
                }

                yield $this->present($case, $number, $result);
            }
        } finally {
            ($session['close'])();
        }

        return [
            'status' => $status,
            'passed' => $passed,
            'total' => count($numbered),
            'duration_ms' => $duration,
        ];
    }

    /**
     * Prepare to run one solution many times.
     *
     * Go function problems are built once here (build time never counts
     * toward a test's limit); everything else runs exactly as before, one
     * {@see CodeRunner::run()} per test.
     *
     * @return array{run: \Closure(?string, float): array<string, mixed>, compile: ?array<string, mixed>, compiled: bool, close: \Closure(): void}
     */
    private function open(?FunctionSignature $sig, string $language, string $code): array
    {
        if ($sig !== null && ! $sig->supports($language)) {
            throw new \InvalidArgumentException('This problem can’t be solved in '.$language.'.');
        }

        if ($sig === null || $language !== 'go') {
            return [
                'run' => fn (?string $input, float $timeout) => $this->execute($sig, $language, $code, $input, $timeout),
                'compile' => null,
                'compiled' => false,
                'close' => fn () => null,
            ];
        }

        $marker = FunctionHarness::marker();
        [$files, $error] = $this->harness->goFiles($sig, $code, $marker);
        if ($files === null) {
            return [
                'run' => fn () => throw new \LogicException('Not compiled.'),
                'compile' => ['ok' => false, 'duration_ms' => 0, 'message' => $error, 'output' => ''],
                'compiled' => true,
                'close' => fn () => null,
            ];
        }

        $program = $this->runner->compile('go', $files);
        $output = trim(preg_replace('/^# playground\n?/m', '', $program->compileOutput));

        return [
            'run' => fn (?string $input, float $timeout) => $this->harness->extract($program->run($input, $timeout), $marker),
            'compile' => [
                'ok' => $program->ok,
                'duration_ms' => $program->compileMs,
                'message' => $program->ok ? null : ($program->compileTimedOut
                    ? 'Compiling took too long.'
                    : FunctionHarness::explainGoCompile($output, $sig)),
                'output' => $program->ok ? '' : $output,
            ],
            'compiled' => true,
            'close' => fn () => $program->dispose(),
        ];
    }

    /**
     * Non-streaming convenience wrapper around {@see cases()}.
     *
     * @return array{status:string, passed:int, total:int, duration_ms:int, cases:array<int, array<string, mixed>>}
     */
    public function judge(Problem $problem, string $language, string $code, bool $visibleOnly = false): array
    {
        $gen = $this->cases($problem, $language, $code, $visibleOnly);
        $rows = [];
        foreach ($gen as $row) {
            $rows[] = $row;
        }

        return $gen->getReturn() + ['cases' => $rows];
    }

    /**
     * Run once with arbitrary stdin (the "Run" button on the custom-input tab).
     * For function problems the stdin is the arguments, one JSON per line, and
     * the result also carries `returned` and `prints`.
     */
    public function runCustom(string $language, string $code, ?string $stdin, ?FunctionSignature $sig = null): array
    {
        $session = $this->open($sig, $language, $code);
        try {
            if ($session['compile'] && ! $session['compile']['ok']) {
                $c = $session['compile'];

                return [
                    'compile_error' => true, 'compile' => $c,
                    'stdout' => '', 'stderr' => trim(($c['message'] ? $c['message']."\n\n" : '').$c['output']),
                    'returned' => null, 'prints' => '', 'exit_code' => null, 'duration_ms' => 0,
                    'timed_out' => false, 'stdout_truncated' => false, 'stderr_truncated' => false,
                ];
            }

            return ($session['run'])($stdin, CodeRunner::TIMEOUT_SECONDS) + ['compile' => $session['compile']];
        } finally {
            ($session['close'])();
        }
    }

    /**
     * Run a (possibly unsaved) reference solution over raw cases. Used by the
     * authoring form to verify and to fill expected outputs. Authors see
     * everything, so nothing is redacted here.
     *
     * @param  list<array{input:?string, expected_output:?string}>  $tests
     * @return \Generator<int, array<string, mixed>>
     */
    public function checkReference(string $language, string $code, array $tests, string $mode, int $timeLimitMs, ?FunctionSignature $sig = null): \Generator
    {
        $session = $this->open($sig, $language, $code);
        try {
            $compile = $session['compile'];
            foreach ($tests as $i => $t) {
                if ($sig && ($error = $sig->decodeArgs($t['input'] ?? null)[1])) {
                    yield ['index' => $i, 'passed' => false, 'status' => 'invalid', 'actual' => null, 'stderr' => $error, 'duration_ms' => 0];

                    continue;
                }
                if ($compile && ! $compile['ok']) {
                    yield [
                        'index' => $i, 'passed' => false, 'status' => ProblemSubmission::STATUS_COMPILE_ERROR, 'actual' => null,
                        'stderr' => trim(($compile['message'] ? $compile['message']."\n" : '').$compile['output']), 'duration_ms' => 0,
                    ];

                    continue;
                }
                $r = ($session['run'])($t['input'] ?? null, $this->timeoutFor($language, $timeLimitMs, $session['compiled']));
                $status = $this->statusOf($r, (string) ($t['expected_output'] ?? ''), $mode, $sig);

                yield [
                    'index' => $i,
                    'passed' => $status === ProblemSubmission::STATUS_ACCEPTED,
                    'status' => $status,
                    'actual' => $sig ? $r['returned'] : $r['stdout'],
                    'stderr' => $r['stderr'],
                    'duration_ms' => $r['duration_ms'],
                ];
            }
        } finally {
            ($session['close'])();
        }
    }

    /**
     * @return array{passed:bool,status:string,actual:string,stderr:string,duration_ms:int}
     */
    private function runCase(Problem $problem, ProblemTestCase $case, array $session, string $language): array
    {
        $sig = $problem->functionSignature();
        $r = ($session['run'])($case->input, $this->timeoutFor($language, $problem->timeLimitMs(), $session['compiled']));
        $status = $this->statusOf($r, $case->expected_output, $problem->compareMode(), $sig);

        $result = [
            'passed' => $status === ProblemSubmission::STATUS_ACCEPTED,
            'status' => $status,
            'actual' => $sig ? $r['returned'] : $r['stdout'],
            'stderr' => $r['stderr'],
            'duration_ms' => $r['duration_ms'],
        ];

        if ($sig) {
            $result['prints'] = $r['prints'];
            $result['hint'] = null;
            if ($status === ProblemSubmission::STATUS_WRONG_ANSWER) {
                $result['hint'] = FunctionSignature::mismatchHint(
                    FunctionSignature::decode($case->expected_output)[1],
                    FunctionSignature::decode((string) $r['returned'])[1],
                    $sig->returns,
                );
            }
        }

        return $result;
    }

    /**
     * Run plain code, or wrap it in the function harness when `$sig` is set.
     *
     * @return array<string, mixed>
     */
    private function execute(?FunctionSignature $sig, string $language, string $code, ?string $input, float $timeout): array
    {
        if ($sig === null) {
            return $this->runner->run($language, $code, $input, $timeout);
        }
        if (! $sig->supports($language)) {
            throw new \InvalidArgumentException('Function-style problems can’t be solved in '.$language.' yet.');
        }

        $marker = FunctionHarness::marker();
        $run = $this->runner->run($language, $this->harness->wrap($language, $sig, $code, $marker), $input, $timeout);

        return $this->harness->extract($run, $marker);
    }

    /**
     * `go run` compiles on every run with a cold, per-run build cache, so the
     * compile time would eat a tight limit. Go always gets the runner maximum.
     */
    private function timeoutFor(string $language, int $limitMs, bool $compiled = false): float
    {
        // Once built ahead of time, Go gets the normal limit like everyone else.
        return $language === 'go' && ! $compiled ? CodeRunner::TIMEOUT_SECONDS : $limitMs / 1000;
    }

    private function statusOf(array $run, string $expected, string $mode, ?FunctionSignature $sig = null): string
    {
        if ($run['timed_out']) {
            return ProblemSubmission::STATUS_TIME_LIMIT;
        }
        if ($run['exit_code'] !== 0) {
            return ProblemSubmission::STATUS_RUNTIME_ERROR;
        }

        if ($sig !== null) {
            if ($run['returned'] === null) {
                return ProblemSubmission::STATUS_RUNTIME_ERROR;
            }
            [, $want] = FunctionSignature::decode($expected);
            [$ok, $got] = FunctionSignature::decode($run['returned']);

            return $ok && FunctionSignature::valuesEqual($want, $got, $sig->returns, $mode === Problem::COMPARE_UNORDERED)
                ? ProblemSubmission::STATUS_ACCEPTED
                : ProblemSubmission::STATUS_WRONG_ANSWER;
        }

        return self::matches($expected, $run['stdout'], $mode)
            ? ProblemSubmission::STATUS_ACCEPTED
            : ProblemSubmission::STATUS_WRONG_ANSWER;
    }

    private function present(ProblemTestCase $case, int $number, array $result): array
    {
        $row = [
            'number' => $number,
            'hidden' => $case->is_hidden,
            'label' => $case->label,
            'passed' => $result['passed'],
            'status' => $result['status'],
            'duration_ms' => $result['duration_ms'],
        ];

        if (! $case->is_hidden) {
            $row += [
                'input' => (string) $case->input,
                'expected' => $case->expected_output,
                'actual' => $result['actual'],
                'stderr' => $result['stderr'],
            ];
            // Function problems: the solver's own prints and a targeted hint.
            // Only ever for visible cases — both could echo hidden data.
            if (array_key_exists('prints', $result)) {
                $row['stdout'] = $result['prints'];
                $row['hint'] = $result['hint'];
            }
        }

        return $row;
    }

    public static function matches(string $expected, string $actual, string $mode): bool
    {
        return match ($mode) {
            Problem::COMPARE_EXACT => $expected === $actual,
            Problem::COMPARE_TOKENS => self::tokens($expected) === self::tokens($actual),
            Problem::COMPARE_FLOAT => self::floatsMatch($expected, $actual),
            default => self::normalize($expected) === self::normalize($actual),
        };
    }

    /** Ignore trailing whitespace on each line and trailing blank lines. */
    public static function normalize(string $text): string
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $lines = array_map(fn (string $l) => rtrim($l), $lines);

        return rtrim(implode("\n", $lines));
    }

    /** @return list<string> */
    private static function tokens(string $text): array
    {
        return preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function floatsMatch(string $expected, string $actual): bool
    {
        $e = self::tokens($expected);
        $a = self::tokens($actual);
        if (count($e) !== count($a)) {
            return false;
        }

        foreach ($e as $i => $tok) {
            if ($tok === $a[$i]) {
                continue;
            }
            if (! is_numeric($tok) || ! is_numeric($a[$i])) {
                return false;
            }
            if (abs((float) $tok - (float) $a[$i]) > self::FLOAT_TOLERANCE * max(1.0, abs((float) $tok))) {
                return false;
            }
        }

        return true;
    }
}
