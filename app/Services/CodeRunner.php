<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Executes user code and returns its captured output.
 *
 * Every program runs inside {@see CodeSandbox}: no network, none of the
 * app's files or secrets, read-only system, resource limits. Each run gets a
 * fresh folder under `storage/app/playground/{uuid}` (read-only to the
 * program) that is always deleted afterwards. The process is killed after
 * {@see TIMEOUT_SECONDS}; output is capped at {@see MAX_OUTPUT_BYTES} per
 * stream.
 *
 * Go is built in a separate sandboxed compile step (with a shared build
 * cache the program itself never sees) and the binary then runs read-only.
 */
class CodeRunner
{
    public const TIMEOUT_SECONDS = 8.0;
    public const MAX_OUTPUT_BYTES = 256 * 1024;
    public const MAX_CODE_BYTES = 200 * 1024;
    public const MAX_STDIN_BYTES = 64 * 1024;
    public const COMPILE_TIMEOUT_SECONDS = 60.0;

    /** Run folders older than this are swept up (e.g. after an aborted stream). */
    private const STALE_DIR_SECONDS = 900;

    public function __construct(private LanguageRuntimeProbe $probe, private CodeSandbox $sandbox) {}

    /**
     * @return array{
     *     language:string,
     *     binary:?string,
     *     stdout:string,
     *     stderr:string,
     *     exit_code:?int,
     *     duration_ms:int,
     *     timed_out:bool,
     *     stdout_truncated:bool,
     *     stderr_truncated:bool,
     * }
     */
    public function run(string $language, string $code, ?string $stdin = null, ?float $timeout = null): array
    {
        $cfg = LanguageRuntimeProbe::LANGUAGES[$language] ?? null;
        if ($cfg === null) {
            throw new \InvalidArgumentException("Unsupported language: {$language}");
        }
        if (! $this->probe->isAvailable($language)) {
            throw new \RuntimeException("Runtime for {$language} is not installed on this host.");
        }
        if (strlen($code) > self::MAX_CODE_BYTES) {
            throw new \InvalidArgumentException('Code exceeds maximum size.');
        }
        if ($stdin !== null && strlen($stdin) > self::MAX_STDIN_BYTES) {
            throw new \InvalidArgumentException('Stdin exceeds maximum size.');
        }

        // The host path of the runtime is not exposed: it reveals the server's layout.
        $meta = ['language' => $language, 'binary' => null];
        $timeout ??= self::TIMEOUT_SECONDS;

        if ($language === 'go') {
            return $meta + $this->runGo($code, $stdin, $timeout);
        }

        $dir = $this->newRunDir();
        try {
            $entry = 'main.'.$cfg['file_extension'];
            File::put($dir.'/'.$entry, $code);

            $process = $this->sandbox->process($this->argv($language, $entry), $dir, $this->envFor($language), [
                'language' => $language,
                'memory_mb' => $language === 'javascript' ? (int) config('sandbox.limits.run.node_memory_mb') : null,
            ]);

            return $meta + self::execute($process, $stdin, $timeout);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /**
     * Build a Go program once so it can be run many times
     * ({@see CompiledProgram}) — per test case when judging.
     *
     * Builds use a shared, persistent build cache (Go no longer ships a
     * pre-built standard library, so an empty cache costs ~2 s per build) and
     * are kept offline and self-contained: no toolchain or module downloads,
     * no stray go.work, and `go.mod` targets the installed Go version.
     *
     * @param  array<string, string>  $files  filename => source
     */
    public function compile(string $language, array $files): CompiledProgram
    {
        if ($language !== 'go') {
            throw new \InvalidArgumentException("{$language} doesn't need compiling.");
        }
        if (! $this->probe->isAvailable($language)) {
            throw new \RuntimeException("Runtime for {$language} is not installed on this host.");
        }
        if (strlen(implode('', $files)) > self::MAX_CODE_BYTES * 2) {
            throw new \InvalidArgumentException('Code exceeds maximum size.');
        }

        $dir = $this->newRunDir();
        File::ensureDirectoryExists($dir.'/.tmp', 0700);
        File::put($dir.'/go.mod', "module playground\n\ngo {$this->goLanguageVersion()}\n");
        foreach ($files as $name => $source) {
            File::put($dir.'/'.basename($name), $source);
        }

        try {
            $process = $this->sandbox->process(
                [$this->sandbox->executable('go'), 'build', '-trimpath', '-o', 'prog', '.'],
                $dir,
                [
                    'GOCACHE' => '{cache}',
                    'GOMODCACHE' => '{work}/.gomodcache',
                    'TMPDIR' => '{work}/.tmp',
                    'GOTOOLCHAIN' => 'local',
                    'GOPROXY' => 'off',
                    'GOWORK' => 'off',
                    'GOENV' => 'off',
                    'GOFLAGS' => '-mod=mod',
                    'GO111MODULE' => 'on',
                    'CGO_ENABLED' => '0',
                ],
                ['language' => 'go', 'writable' => true, 'cache' => true, 'profile' => 'compile'],
            );
        } catch (\Throwable $e) {
            File::deleteDirectory($dir);
            throw $e;
        }

        $process->setTimeout(self::COMPILE_TIMEOUT_SECONDS);
        $start = microtime(true);
        $timedOut = false;
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        }
        $ms = (int) round((microtime(true) - $start) * 1000);
        [$output] = self::capture(trim($process->getErrorOutput()."\n".$process->getOutput()));
        $ok = ! $timedOut && $process->getExitCode() === 0 && is_file($dir.'/prog');

        $program = new CompiledProgram($ok, $output, $ms, $timedOut, $dir, $this->sandbox);
        if (! $ok) {
            $program->dispose();
        }

        return $program;
    }

    /**
     * Run a prepared process with stdin and a timeout, capturing its output.
     *
     * @return array{stdout:string, stderr:string, exit_code:?int, duration_ms:int, timed_out:bool, stdout_truncated:bool, stderr_truncated:bool}
     */
    public static function execute(Process $process, ?string $stdin, float $timeout): array
    {
        $process->setTimeout(min($timeout, self::TIMEOUT_SECONDS));
        if ($stdin !== null && $stdin !== '') {
            $process->setInput($stdin);
        }

        $start = microtime(true);
        $timedOut = false;
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        }
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        [$stdout, $stdoutTrunc] = self::capture($process->getOutput());
        [$stderr, $stderrTrunc] = self::capture($process->getErrorOutput());

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $process->getExitCode(),
            'duration_ms' => $durationMs,
            'timed_out' => $timedOut,
            'stdout_truncated' => $stdoutTrunc,
            'stderr_truncated' => $stderrTrunc,
        ];
    }

    /**
     * @return array{0:string,1:bool}
     */
    public static function capture(string $stream): array
    {
        if (strlen($stream) <= self::MAX_OUTPUT_BYTES) {
            return [$stream, false];
        }

        return [substr($stream, 0, self::MAX_OUTPUT_BYTES), true];
    }

    /** Playground / stdin problems: build `main.go`, then run it like any other program. */
    private function runGo(string $code, ?string $stdin, float $timeout): array
    {
        $program = $this->compile('go', ['main.go' => $code]);
        try {
            if (! $program->ok) {
                $output = trim(preg_replace('/^# playground\n?/m', '', $program->compileOutput));

                return [
                    'stdout' => '',
                    'stderr' => $program->compileTimedOut ? 'Compiling took too long.' : $output,
                    'exit_code' => 1,
                    'duration_ms' => $program->compileMs,
                    'timed_out' => false,
                    'stdout_truncated' => false,
                    'stderr_truncated' => false,
                ];
            }

            return $program->run($stdin, $timeout);
        } finally {
            $program->dispose();
        }
    }

    /** @return list<string> */
    private function argv(string $language, string $entry): array
    {
        $exe = $this->sandbox->executable($language);

        return match ($language) {
            'javascript' => [$exe, '--max-old-space-size='.(int) config('sandbox.limits.run.node_heap_mb'), $entry],
            'python' => [$exe, '-u', $entry],
            'php' => [$exe, '-d', 'memory_limit=256M', '-d', 'error_reporting=E_ALL', '-d', 'display_errors=stderr', $entry],
            default => throw new \InvalidArgumentException("Unsupported language: {$language}"),
        };
    }

    /** @return array<string, string> */
    private function envFor(string $language): array
    {
        return match ($language) {
            'python' => ['PYTHONDONTWRITEBYTECODE' => '1', 'PYTHONIOENCODING' => 'utf-8'],
            default => [],
        };
    }

    /** "1.27" from "go version go1.27.4 linux/amd64"; falls back to 1.21. */
    private function goLanguageVersion(): string
    {
        $version = (string) ($this->probe->detect()['go']['version'] ?? '');

        return preg_match('/go(\d+)\.(\d+)/', $version, $m) ? "{$m[1]}.{$m[2]}" : '1.21';
    }

    private function newRunDir(): string
    {
        $this->sweepStaleDirs();
        $dir = storage_path('app/playground/'.Str::uuid());
        File::ensureDirectoryExists($dir, 0700);

        return $dir;
    }

    private function sweepStaleDirs(): void
    {
        $root = storage_path('app/playground');
        if (! is_dir($root)) {
            return;
        }
        foreach (File::directories($root) as $d) {
            if (@filemtime($d) < time() - self::STALE_DIR_SECONDS) {
                File::deleteDirectory($d);
            }
        }
    }
}
