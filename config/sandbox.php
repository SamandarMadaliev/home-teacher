<?php

/*
 * Code sandbox for the Playground and Problems (see App\Services\CodeSandbox).
 *
 * "bwrap" runs every user program inside bubblewrap: no network, no access to
 * the app's files or environment, read-only system, its own process space and
 * resource limits. If the sandbox doesn't work, code doesn't run (fail closed).
 *
 * "none" runs programs directly as the web-server user. Only use it on a
 * private machine where you are the only person who can sign in.
 */
return [
    'driver' => env('CODE_SANDBOX', 'bwrap'),

    'bwrap_binary' => env('CODE_SANDBOX_BWRAP', '/usr/bin/bwrap'),
    'prlimit_binary' => env('CODE_SANDBOX_PRLIMIT', '/usr/bin/prlimit'),

    // Limits for a user's program. Memory is the data-segment limit; Node gets
    // more because V8 reserves address space up front, and its real heap is
    // capped separately with --max-old-space-size.
    'limits' => [
        'run' => [
            'memory_mb' => 512,
            'node_memory_mb' => 1024,
            'node_heap_mb' => 256,
            'processes' => 64,
            'file_size_mb' => 16,
            'tmp_mb' => 16,
            'open_files' => 128,
            'cpu_seconds' => 10,
        ],
        // The Go compiler (trusted, but still sandboxed) needs more room.
        'compile' => [
            'memory_mb' => 2048,
            'processes' => 512,
            'file_size_mb' => 256,
            'tmp_mb' => 64,
            'open_files' => 1024,
            'cpu_seconds' => 120,
        ],
    ],
];
