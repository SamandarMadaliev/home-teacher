<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CodeRunner;
use App\Services\CodeSandbox;
use App\Services\LanguageRuntimeProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User code must not be able to read the app's secrets or files, reach the
 * network, outlive its run or exhaust the machine. Each probe prints only
 * "blocked"/"EXPOSED" — never file contents.
 */
class CodeSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('HT_TEST_SECRET=do-not-leak');
        $_ENV['HT_TEST_SECRET'] = 'do-not-leak';

        if (! app(LanguageRuntimeProbe::class)->isAvailable('python')) {
            $this->markTestSkipped('python3 not installed');
        }
        app(CodeSandbox::class)->forget();
        try {
            app(CodeSandbox::class)->assertUsable();
        } catch (\RuntimeException) {
            $this->markTestSkipped('bubblewrap sandbox not usable here');
        }
    }

    protected function tearDown(): void
    {
        putenv('HT_TEST_SECRET');
        unset($_ENV['HT_TEST_SECRET']);
        parent::tearDown();
    }

    private function python(string $code): array
    {
        return app(CodeRunner::class)->run('python', $code);
    }

    private function probe(array $checks): string
    {
        $lines = ["import os, socket\ndef check(label, fn):\n    try:\n        fn(); print(label, 'EXPOSED')\n    except Exception:\n        print(label, 'blocked')"];
        foreach ($checks as $label => $expr) {
            $lines[] = "check('{$label}', lambda: {$expr})";
        }

        return implode("\n", $lines)."\n";
    }

    public function test_user_code_cannot_reach_secrets_files_or_network(): void
    {
        $r = $this->python($this->probe([
            'env' => "[k for k in os.environ if 'SECRET' in k or 'KEY' in k][0]",
            'dotenv' => "open('".base_path('.env')."').read(1)",
            'database' => "open('".database_path('database.sqlite')."', 'rb').read(1)",
            'storage' => "os.listdir('".storage_path()."')",
            'home' => "os.listdir('/home')",
            'buildcache' => "os.listdir('".storage_path('app/go-build-cache')."')",
            'network' => "socket.create_connection(('1.1.1.1', 53), timeout=2)",
            'write_usr' => "open('/usr/pwned', 'w')",
            'write_work' => "open('/work/pwned', 'w')",
        ]));

        foreach (['env', 'dotenv', 'database', 'storage', 'home', 'buildcache', 'network', 'write_usr', 'write_work'] as $label) {
            $this->assertStringContainsString("{$label} blocked", $r['stdout'], $r['stdout'].$r['stderr']);
        }
        $this->assertStringNotContainsString('do-not-leak', $r['stdout']);
    }

    public function test_program_sees_no_other_processes_and_leaves_none_behind(): void
    {
        $r = $this->python("import os\nprint(len([p for p in os.listdir('/proc') if p.isdigit()]))\nif os.fork() == 0:\n    os.setsid()\n    import time; time.sleep(30)\n");
        $this->assertLessThanOrEqual(3, (int) trim($r['stdout']));

        usleep(300_000);
        exec("ps -eo args | grep -c '^/usr/bin/python3 -u main.py'", $out);
        $this->assertSame('0', trim($out[0] ?? '0'), 'a background process outlived its run');
    }

    public function test_resource_attacks_are_contained(): void
    {
        $fork = $this->python("import os, time\nn = 0\ntry:\n    while True:\n        if os.fork() == 0:\n            time.sleep(2); os._exit(0)\n        n += 1\nexcept OSError:\n    print('forks', n)\n");
        $this->assertMatchesRegularExpression('/forks (\d+)/', $fork['stdout']);
        preg_match('/forks (\d+)/', $fork['stdout'], $m);
        $this->assertLessThan(100, (int) $m[1]);

        $memory = $this->python("b = []\ntry:\n    for i in range(64): b.append(bytearray(64 * 1024 * 1024))\n    print('unlimited')\nexcept MemoryError:\n    print('limited', len(b) * 64)\n");
        $this->assertStringContainsString('limited', $memory['stdout']);
        $this->assertStringNotContainsString('unlimited', $memory['stdout']);

        $disk = $this->python("n = 0\ntry:\n    f = open('/tmp/fill', 'wb')\n    for i in range(200): f.write(b'x' * 1024 * 1024); f.flush(); n += 1\n    print('unlimited')\nexcept OSError:\n    print('stopped', n)\n");
        $this->assertStringContainsString('stopped', $disk['stdout']);

        $spin = app(CodeRunner::class)->run('python', "while True: pass\n", null, 1.0);
        $this->assertTrue($spin['timed_out']);
    }

    public function test_every_language_runs_inside_the_sandbox(): void
    {
        $probe = app(LanguageRuntimeProbe::class);
        $programs = [
            'javascript' => "console.log(require('fs').existsSync('".base_path('.env')."') ? 'EXPOSED' : 'blocked')",
            'php' => "<?php echo file_exists('".base_path('.env')."') ? 'EXPOSED' : 'blocked';",
            'go' => "package main\n\nimport (\"fmt\"\n\"os\")\n\nfunc main() {\n\t_, err := os.Stat(\"".base_path('.env')."\")\n\t_, err2 := os.Stat(\"/cache\")\n\tif err != nil && err2 != nil { fmt.Print(\"blocked\") } else { fmt.Print(\"EXPOSED\") }\n}\n",
        ];
        foreach ($programs as $lang => $code) {
            if (! $probe->isAvailable($lang)) {
                continue;
            }
            $r = app(CodeRunner::class)->run($lang, $code);
            $this->assertSame('blocked', trim($r['stdout']), "{$lang}: ".$r['stdout'].$r['stderr']);
        }
    }

    public function test_fails_closed_when_the_sandbox_is_broken(): void
    {
        config(['sandbox.bwrap_binary' => '/nonexistent/bwrap']);
        app(CodeSandbox::class)->forget();

        try {
            $this->python("print('ran')");
            $this->fail('code ran without a sandbox');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('turned off', $e->getMessage());
        }

        $this->actingAs(User::factory()->create())
            ->postJson(route('playground.run'), ['language' => 'python', 'code' => "print('ran')"])
            ->assertStatus(422)->assertJsonPath('error', 'run_failed');
    }

    public function test_without_the_sandbox_the_environment_is_still_scrubbed(): void
    {
        config(['sandbox.driver' => 'none']);

        $r = $this->python("import os\nprint(sorted(k for k in os.environ if 'SECRET' in k or 'APP_' in k))\n");

        $this->assertSame('[]', trim($r['stdout']), $r['stderr']);
    }
}
