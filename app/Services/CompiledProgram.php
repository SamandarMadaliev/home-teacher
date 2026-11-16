<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * A program built once by {@see CodeRunner::compile()} and then run many
 * times (once per test case), each time in a fresh read-only sandbox that
 * never sees the build cache. Build time never counts toward a test's time
 * limit. Call {@see dispose()} when done; the destructor is a backstop.
 */
class CompiledProgram
{
    private bool $disposed = false;

    public function __construct(
        public readonly bool $ok,
        public readonly string $compileOutput,
        public readonly int $compileMs,
        public readonly bool $compileTimedOut,
        private readonly string $dir,
        private readonly CodeSandbox $sandbox,
    ) {}

    /**
     * Same result shape as {@see CodeRunner::execute()}.
     *
     * @return array<string, mixed>
     */
    public function run(?string $stdin, float $timeout): array
    {
        if (! $this->ok || $this->disposed) {
            throw new \LogicException('Program is not runnable.');
        }

        return CodeRunner::execute(
            $this->sandbox->process(['./prog'], $this->dir, ['GOMAXPROCS' => '2']),
            $stdin,
            $timeout,
        );
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
