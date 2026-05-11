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
    public function run(string $language, string $code, ?string $stdin = null): array
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
            $process->setTimeout(self::TIMEOUT_SECONDS);
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

            [$stdout, $stdoutTrunc] = $this->capture($process->getOutput());
            [$stderr, $stderrTrunc] = $this->capture($process->getErrorOutput());

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
    private function capture(string $stream): array
    {
        if (strlen($stream) <= self::MAX_OUTPUT_BYTES) {
            return [$stream, false];
        }

        return [substr($stream, 0, self::MAX_OUTPUT_BYTES), true];
    }
}
