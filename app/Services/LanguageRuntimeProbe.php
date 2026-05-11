<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Detects which playground language runtimes are installed on this host.
 *
 * Probing shells out to each interpreter/compiler once, then caches the answer
 * so we don't keep re-running `node --version` on every page render.
 *
 * Available languages drive what's offered in the UI — if a runtime is missing,
 * the playground hides that language and shows install hints instead.
 */
class LanguageRuntimeProbe
{
    private const CACHE_KEY = 'playground.runtimes.v1';
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Display metadata per language. Order is the UI order too.
     *
     * - `binary`   primary executable to probe / run with.
     * - `fallbacks` alternative names to try if the primary isn't on PATH.
     * - `version_args` arguments that ask the binary to print its version.
     * - `version_must_contain` substring that must appear in stdout/stderr,
     *   used to reject e.g. Python 2.x masquerading as `python`.
     */
    public const LANGUAGES = [
        'javascript' => [
            'label' => 'JavaScript',
            'binary' => 'node',
            'fallbacks' => [],
            'version_args' => ['--version'],
            'version_must_contain' => 'v',
            'file_extension' => 'js',
            'sample' => "console.log('Hello from JavaScript!');\n",
        ],
        'python' => [
            'label' => 'Python',
            'binary' => 'python3',
            'fallbacks' => ['python'],
            'version_args' => ['--version'],
            'version_must_contain' => 'Python 3',
            'file_extension' => 'py',
            'sample' => "print('Hello from Python!')\n",
        ],
        'php' => [
            'label' => 'PHP',
            'binary' => 'php',
            'fallbacks' => [],
            'version_args' => ['--version'],
            'version_must_contain' => 'PHP',
            'file_extension' => 'php',
            'sample' => "<?php\n\necho \"Hello from PHP!\\n\";\n",
        ],
        'go' => [
            'label' => 'Go',
            'binary' => 'go',
            'fallbacks' => [],
            'version_args' => ['version'],
            'version_must_contain' => 'go version',
            'file_extension' => 'go',
            'sample' => "package main\n\nimport \"fmt\"\n\nfunc main() {\n    fmt.Println(\"Hello from Go!\")\n}\n",
        ],
    ];

    public function __construct(private ExecutableFinder $finder = new ExecutableFinder()) {}

    /**
     * Probe every supported language, using the cached result when present.
     *
     * @return array<string, array{label:string,available:bool,binary:?string,version:?string,sample:string}>
     */
    public function detect(bool $force = false): array
    {
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->probeAll());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array{key:string,label:string,available:bool,binary:?string,version:?string,sample:string}>
     */
    public function detectList(bool $force = false): array
    {
        $rows = [];
        foreach ($this->detect($force) as $key => $info) {
            $rows[] = ['key' => $key] + $info;
        }

        return $rows;
    }

    public function isAvailable(string $language): bool
    {
        $row = $this->detect()[$language] ?? null;

        return (bool) ($row['available'] ?? false);
    }

    /**
     * Resolved absolute path of the runtime binary, or `null` when unavailable.
     */
    public function binary(string $language): ?string
    {
        return $this->detect()[$language]['binary'] ?? null;
    }

    /**
     * @return array<string, array{label:string,available:bool,binary:?string,version:?string,sample:string}>
     */
    private function probeAll(): array
    {
        $out = [];
        foreach (self::LANGUAGES as $key => $cfg) {
            $out[$key] = $this->probeOne($cfg);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array{label:string,available:bool,binary:?string,version:?string,sample:string}
     */
    private function probeOne(array $cfg): array
    {
        $candidates = array_merge([$cfg['binary']], $cfg['fallbacks'] ?? []);

        foreach ($candidates as $name) {
            $path = $this->finder->find($name);
            if ($path === null) {
                continue;
            }

            $version = $this->extractVersion($path, $cfg['version_args'], $cfg['version_must_contain'] ?? null);
            if ($version === null) {
                continue;
            }

            return [
                'label' => $cfg['label'],
                'available' => true,
                'binary' => $path,
                'version' => $version,
                'sample' => $cfg['sample'],
            ];
        }

        return [
            'label' => $cfg['label'],
            'available' => false,
            'binary' => null,
            'version' => null,
            'sample' => $cfg['sample'],
        ];
    }

    /**
     * Run `binary <version args>` with a short timeout and return the first
     * non-empty version line, or `null` if probing fails or output doesn't
     * contain the expected marker.
     *
     * @param  array<int, string>  $args
     */
    private function extractVersion(string $binary, array $args, ?string $mustContain): ?string
    {
        $process = new Process([$binary, ...$args]);
        $process->setTimeout(3.0);
        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        $combined = trim($process->getOutput()."\n".$process->getErrorOutput());
        if ($combined === '') {
            return null;
        }

        if ($mustContain !== null && ! str_contains($combined, $mustContain)) {
            return null;
        }

        $firstLine = strtok($combined, "\n");

        return $firstLine !== false ? trim($firstLine) : trim($combined);
    }
}
