<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * A program built once by {@see CodeRunner::compile()} and then run many
 * times (once per test case). Build time never counts toward a test's
 * time limit. Call {@see dispose()} when done; the destructor is a backstop.
 */
class CompiledProgram
{
    private bool $disposed = false;

    /**
     * @param  list<string>  $command  absolute command to execute the built program
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $compileOutput,
        public readonly int $compileMs,
        public readonly bool $compileTimedOut,
        private readonly string $dir,
        private readonly array $command,
    ) {}

    /**
     * Same result shape as {@see CodeRunner::run()}.
     *
     * @return array<string, mixed>
     */
    public function run(?string $stdin, float $timeout): array
    {
        if (! $this->ok || $this->disposed) {
            throw new \LogicException('Program is not runnable.');
        }

        $process = new Process($this->command, $this->dir, ['HOME' => $this->dir]);
        $process->setTimeout(min($timeout, CodeRunner::TIMEOUT_SECONDS));
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

        [$stdout, $stdoutTrunc] = CodeRunner::capture($process->getOutput());
        [$stderr, $stderrTrunc] = CodeRunner::capture($process->getErrorOutput());

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

    public function dispose(): void
    {
        if (! $this->disposed) {
            $this->disposed = true;
            File::deleteDirectory($this->dir);
        }
    }

    public function __destruct()
    {
        $this->dispose();
    }
}
