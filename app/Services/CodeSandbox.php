<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Wraps every program the app runs on behalf of a user (Playground, problem
 * runs, judging, reference checks) in a bubblewrap sandbox.
 *
 * Inside, the program sees only the system's /usr (read-only), its language
 * runtime (read-only, at /opt/<language>), its run folder at /work (read-only
 * unless compiling) and a small private /tmp. It gets no network, no view of
 * other processes or of the app's files, and none of the app's environment
 * variables (which hold APP_KEY, OAuth and mail secrets). Memory, processes,
 * file size, open files and CPU time are limited, and everything it starts
 * dies with it.
 *
 * With driver "none" programs run directly (trusted single-user machines
 * only) — the environment is still scrubbed. Driver "bwrap" fails closed:
 * if the sandbox doesn't work, {@see wrap()} throws instead of running code.
 */
class CodeSandbox
{
    private const ROOTS_CACHE_KEY = 'sandbox.runtime-roots.v1';

    private const HEALTH_CACHE_KEY = 'sandbox.health.v1';

    public function __construct(private LanguageRuntimeProbe $probe) {}

    public function enabled(): bool
    {
        return config('sandbox.driver') !== 'none';
    }

    /**
     * Path of a language's executable as the program will see it.
     */
    public function executable(string $language): string
    {
        if (! $this->enabled()) {
            return (string) $this->probe->binary($language);
        }
        $mount = $this->runtimeMount($language);

        return $mount['executable'];
    }

    /**
     * Build the process for `$argv` (relative to the run folder).
     *
     * `$env` values may use `{work}` and `{cache}`, replaced with the run
     * folder and the shared Go build cache as the program sees them.
     *
     * @param  list<string>  $argv
     * @param  array<string, string>  $env
     * @param  array{language?: ?string, writable?: bool, cache?: bool, profile?: 'run'|'compile', memory_mb?: int, skip_health?: bool}  $options
     */
    public function process(array $argv, string $workDir, array $env = [], array $options = []): Process
    {
        $profile = $options['profile'] ?? 'run';
        $limits = config("sandbox.limits.{$profile}") + config('sandbox.limits.run');
        $cacheDir = storage_path('app/go-build-cache');
        if ($options['cache'] ?? false) {
            File::ensureDirectoryExists($cacheDir, 0700);
        }

        if (! $this->enabled()) {
            $vars = $this->expand($env + ['HOME' => '{work}', 'LANG' => 'C.UTF-8'], $workDir, $cacheDir)
                + ['PATH' => (string) getenv('PATH')];

            return new Process($argv, $workDir, self::scrubbedEnv($vars));
        }

        if (! ($options['skip_health'] ?? false)) {
            $this->assertUsable();
        }

        $language = $options['language'] ?? null;
        $mount = $language ? $this->runtimeMount($language) : null;
        $path = ($mount && $mount['host'] ? $mount['inside'].'/bin:' : '').'/usr/local/bin:/usr/bin:/bin';
        $vars = $this->expand($env + ['HOME' => '{work}', 'LANG' => 'C.UTF-8', 'PATH' => $path], '/work', '/cache');

        $bwrap = [
            '--unshare-all', '--unshare-user', '--disable-userns',
            '--die-with-parent', '--new-session', '--cap-drop', 'ALL',
            '--hostname', 'sandbox', '--clearenv',
        ];
        foreach ($vars as $k => $v) {
            array_push($bwrap, '--setenv', $k, $v);
        }

        array_push($bwrap, '--ro-bind', '/usr', '/usr');
        foreach (['/bin', '/lib', '/lib64', '/sbin'] as $dir) {
            if (is_link($dir)) {
                array_push($bwrap, '--symlink', (string) readlink($dir), $dir);
            } elseif (is_dir($dir)) {
                array_push($bwrap, '--ro-bind', $dir, $dir);
            }
        }
        foreach (['/etc/ld.so.cache', '/etc/ld.so.conf', '/etc/ld.so.conf.d', '/etc/alternatives', '/etc/localtime'] as $etc) {
            array_push($bwrap, '--ro-bind-try', $etc, $etc);
        }
        if ($language === 'php') {
            array_push($bwrap, '--ro-bind-try', '/etc/php', '/etc/php');
        }
        if ($mount && $mount['host']) {
            array_push($bwrap, '--ro-bind', $mount['host'], $mount['inside']);
        }

        array_push(
            $bwrap,
            '--proc', '/proc', '--dev', '/dev',
            '--size', (string) ($limits['tmp_mb'] * 1024 * 1024), '--tmpfs', '/tmp',
            ($options['writable'] ?? false) ? '--bind' : '--ro-bind', $workDir, '/work',
        );
        if ($options['cache'] ?? false) {
            array_push($bwrap, '--bind', $cacheDir, '/cache');
        }
        array_push($bwrap, '--chdir', '/work', '--');

        $memoryMb = $options['memory_mb'] ?? $limits['memory_mb'];
        $prlimit = (string) config('sandbox.prlimit_binary');

        // RLIMIT_NPROC counts every process of the user, so it is applied
        // inside the sandbox, where the count starts fresh.
        $command = [
            $prlimit,
            '--nofile='.$limits['open_files'],
            '--fsize='.($limits['file_size_mb'] * 1024 * 1024),
            '--core=0',
            '--cpu='.$limits['cpu_seconds'],
            '--data='.($memoryMb * 1024 * 1024),
            '--',
            (string) config('sandbox.bwrap_binary'),
            ...$bwrap,
            $prlimit, '--nproc='.$limits['processes'], '--',
            ...$argv,
        ];

        return new Process($command, $workDir, self::scrubbedEnv(['PATH' => '/usr/bin:/bin']));
    }

    /**
     * Throw (and log the reason) unless the sandbox can start a program.
     * Successes are remembered for an hour, failures for a minute.
     */
    public function assertUsable(): void
    {
        $cached = Cache::get(self::HEALTH_CACHE_KEY);
        if ($cached === null) {
            $error = $this->selfTest();
            $cached = ['ok' => $error === null, 'error' => $error];
            Cache::put(self::HEALTH_CACHE_KEY, $cached, $error === null ? 3600 : 60);
            if ($error !== null) {
                Log::error('Code sandbox is not usable; running code is disabled.', ['reason' => $error]);
            }
        }

        if (! $cached['ok']) {
            throw new \RuntimeException('Running code is turned off on this server because its safety sandbox isn’t working. The site admin can find the reason in the log.');
        }
    }

    public function forget(): void
    {
        Cache::forget(self::HEALTH_CACHE_KEY);
        Cache::forget(self::ROOTS_CACHE_KEY);
    }

    private function selfTest(): ?string
    {
        foreach (['bwrap_binary', 'prlimit_binary'] as $key) {
            $bin = (string) config("sandbox.{$key}");
            if (! is_file($bin) || ! is_executable($bin)) {
                return "{$bin} is missing or not executable";
            }
        }

        $dir = storage_path('app/playground/'.Str::uuid());
        File::ensureDirectoryExists($dir, 0700);
        try {
            $process = $this->process(['/usr/bin/true'], $dir, [], ['skip_health' => true]);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                return null;
            }

            return trim($process->getErrorOutput()) ?: 'exit code '.$process->getExitCode();
        } catch (\Throwable $e) {
            return $e->getMessage();
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /**
     * Where a language's runtime lives on the host and how it appears inside
     * the sandbox. Runtimes under /usr are already visible; anything else
     * (nvm's Node, the Go snap, …) is mounted read-only at /opt/<language>,
     * so the sandbox never sees the host's home directories.
     *
     * @return array{host: ?string, inside: ?string, executable: string}
     */
    private function runtimeMount(string $language): array
    {
        $roots = Cache::get(self::ROOTS_CACHE_KEY, []);
        if (isset($roots[$language])) {
            return $roots[$language];
        }

        $binary = $this->probe->binary($language);
        if ($binary === null) {
            throw new \RuntimeException("Runtime for {$language} is not installed on this host.");
        }

        if ($language === 'go') {
            // The binary on PATH may be a launcher (e.g. /snap/bin/go → snap),
            // so ask Go itself where its toolchain lives.
            $p = new Process([$binary, 'env', 'GOROOT']);
            $p->setTimeout(15);
            $p->run();
            $root = realpath(trim($p->getOutput()));
            if ($root === false || ! is_file($root.'/bin/go')) {
                throw new \RuntimeException('Could not find the Go toolchain (GOROOT).');
            }
            $mount = ['host' => $root, 'inside' => '/opt/go', 'executable' => '/opt/go/bin/go'];
        } else {
            $real = (string) realpath($binary);
            if (str_starts_with($real, '/usr/')) {
                $mount = ['host' => null, 'inside' => null, 'executable' => $real];
            } else {
                $prefix = dirname($real, 2);
                $mount = [
                    'host' => $prefix,
                    'inside' => "/opt/{$language}",
                    'executable' => "/opt/{$language}/".ltrim(substr($real, strlen($prefix)), '/'),
                ];
            }
        }

        $roots[$language] = $mount;
        Cache::put(self::ROOTS_CACHE_KEY, $roots, 3600);

        return $mount;
    }

    /**
     * @param  array<string, string>  $env
     * @return array<string, string>
     */
    private function expand(array $env, string $work, string $cache): array
    {
        return array_map(fn ($v) => str_replace(['{work}', '{cache}'], [$work, $cache], $v), $env);
    }

    /**
     * An environment for Symfony Process that contains only `$wanted`: every
     * inherited variable (the app's .env secrets live there) is unset.
     *
     * @param  array<string, string>  $wanted
     * @return array<string, string|false>
     */
    public static function scrubbedEnv(array $wanted): array
    {
        $inherited = array_keys(getenv() + $_ENV + array_filter($_SERVER, 'is_string'));

        return $wanted + array_fill_keys($inherited, false);
    }
}
