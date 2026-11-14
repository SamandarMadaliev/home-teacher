<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Executes a snippet of code locally and returns its captured output.
 *
 * Each run gets a fresh directory under `storage/app/playground/{uuid}` that
 * holds `main.{ext}` (plus a `go.mod` for Go runs). The process is killed if
 * it exceeds {@see TIMEOUT_SECONDS}; output streams are capped at
 * {@see MAX_OUTPUT_BYTES} per channel so a runaway loop can't blow up the
 * response. The temp directory is always deleted afterwards.
 */
class CodeRunner
{
    public const TIMEOUT_SECONDS = 8.0;
    public const MAX_OUTPUT_BYTES = 256 * 1024;
    public const MAX_CODE_BYTES = 200 * 1024;
    public const MAX_STDIN_BYTES = 64 * 1024;
    public const COMPILE_TIMEOUT_SECONDS = 60.0;

    /** Build folders older than this are swept up (e.g. after an aborted stream). */
    private const STALE_DIR_SECONDS = 900;

    public function __construct(private LanguageRuntimeProbe $probe) {}

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

        $binary = $this->probe->binary($language);
        if ($binary === null) {
            throw new \RuntimeException("No binary path resolved for {$language}.");
        }

        $dir = storage_path('app/playground/'.Str::uuid());
        File::ensureDirectoryExists($dir, 0700);

        try {
            $entrypoint = $this->writeSources($dir, $language, $cfg, $code);
            $cmd = $this->buildCommand($language, $binary, $entrypoint);

            $process = new Process($cmd, $dir, $this->envFor($language, $dir));
            $process->setTimeout(min($timeout ?? self::TIMEOUT_SECONDS, self::TIMEOUT_SECONDS));
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
                'language' => $language,
                'binary' => $binary,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'exit_code' => $process->getExitCode(),
                'duration_ms' => $durationMs,
                'timed_out' => $timedOut,
                'stdout_truncated' => $stdoutTrunc,
                'stderr_truncated' => $stderrTrunc,
            ];
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /**
     * Build a multi-file program once so it can be run per test case
     * ({@see CompiledProgram}). Only Go needs this today.
     *
     * Judge builds use a shared, persistent build cache (Go no longer ships a
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
        if (! $this->probe->isAvailable($language) || ($binary = $this->probe->binary($language)) === null) {
            throw new \RuntimeException("Runtime for {$language} is not installed on this host.");
        }
        if (strlen(implode('', $files)) > self::MAX_CODE_BYTES * 2) {
            throw new \InvalidArgumentException('Code exceeds maximum size.');
        }

        $this->sweepStaleDirs();
        $dir = storage_path('app/playground/'.Str::uuid());
        File::ensureDirectoryExists($dir, 0700);

        File::put($dir.'/go.mod', "module playground\n\ngo {$this->goLanguageVersion()}\n");
        foreach ($files as $name => $source) {
            File::put($dir.'/'.basename($name), $source);
        }

        $process = new Process([$binary, 'build', '-trimpath', '-o', 'prog', '.'], $dir, $this->goJudgeEnv($dir));
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

        $program = new CompiledProgram($ok, $output, $ms, $timedOut, $dir, [$dir.'/prog']);
        if (! $ok) {
            $program->dispose();
        }

        return $program;
    }

    /** "1.27" from "go version go1.27.4 linux/amd64"; falls back to 1.21. */
    private function goLanguageVersion(): string
    {
        $version = (string) ($this->probe->detect()['go']['version'] ?? '');

        return preg_match('/go(\d+)\.(\d+)/', $version, $m) ? "{$m[1]}.{$m[2]}" : '1.21';
    }

    /** @return array<string, string> */
    private function goJudgeEnv(string $dir): array
    {
        $cache = storage_path('app/go-build-cache');
        File::ensureDirectoryExists($cache, 0700);

        return [
            'HOME' => $dir,
            'GOCACHE' => $cache,
            'GOMODCACHE' => $dir.DIRECTORY_SEPARATOR.'.gomodcache',
            'GOTOOLCHAIN' => 'local',
            'GOPROXY' => 'off',
            'GOWORK' => 'off',
            'GOFLAGS' => '-mod=mod',
            'GO111MODULE' => 'on',
            'CGO_ENABLED' => '0',
        ];
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

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function writeSources(string $dir, string $language, array $cfg, string $code): string
    {
        $filename = 'main.'.$cfg['file_extension'];
        File::put($dir.'/'.$filename, $code);

        if ($language === 'go') {
            File::put($dir.'/go.mod', "module playground\n\ngo 1.21\n");
        }

        return $filename;
    }

    /**
     * @return array<int, string>
     */
    private function buildCommand(string $language, string $binary, string $entrypoint): array
    {
        return match ($language) {
            'javascript' => [$binary, $entrypoint],
            'python' => [$binary, '-u', $entrypoint],
            'php' => [$binary, '-d', 'error_reporting=E_ALL', '-d', 'display_errors=stderr', $entrypoint],
            'go' => [$binary, 'run', $entrypoint],
            default => throw new \InvalidArgumentException("Unsupported language: {$language}"),
        };
    }

    /**
     * Environment overrides merged by Symfony Process with the default env.
     *
     * Go needs a writable {@see https://pkg.go.dev/cmd/go#hdr-Build_cache GOCACHE}
     * and a real {@see https://pkg.go.dev/os#UserConfigDir HOME} — PHP often runs
     * without `HOME` set (Herd, php-fpm, sandboxed workers), which triggers:
     * "GOCACHE is not defined and $HOME is not defined". We pin both inside the
     * ephemeral workspace so every run is self-contained and writable.
     *
     * @return array<string, string>
     */
    private function envFor(string $language, string $workspaceDir): array
    {
        return match ($language) {
            'python' => ['PYTHONDONTWRITEBYTECODE' => '1', 'PYTHONIOENCODING' => 'utf-8'],
            'go' => $this->goEnv($workspaceDir),
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function goEnv(string $workspaceDir): array
    {
        $gocache = $workspaceDir.DIRECTORY_SEPARATOR.'.gocache';
        $gomodcache = $workspaceDir.DIRECTORY_SEPARATOR.'.gomodcache';
        File::ensureDirectoryExists($gocache, 0700);
        File::ensureDirectoryExists($gomodcache, 0700);

        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? $_ENV['HOME'] ?? null);
        if (! is_string($home) || $home === '') {
            $home = PHP_OS_FAMILY === 'Windows'
                ? (getenv('USERPROFILE') ?: $workspaceDir)
                : $workspaceDir;
        }

        return [
            'HOME' => $home,
            'GOCACHE' => $gocache,
            'GOMODCACHE' => $gomodcache,
            'GOFLAGS' => '-mod=mod',
            'GO111MODULE' => 'on',
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
}
