<?php

namespace Tests\Feature;

use App\Models\Problem;
use App\Models\User;
use App\Services\LanguageRuntimeProbe;
use App\Support\FunctionSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LeetCode-style problems: the solver writes one function, its return value is judged. */
class ProblemFunctionModeTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return $over + [
            'title' => 'Two sum',
            'difficulty' => 'easy',
            'description' => 'Return the indices.',
            'io_mode' => 'function',
            'compare_mode' => 'exact',
            'signature' => [
                'name' => 'twoSum',
                'params' => [['name' => 'nums', 'type' => 'int[]'], ['name' => 'target', 'type' => 'int']],
                'returns' => 'int[]',
            ],
            'tests' => [
                ['input' => "[2, 7, 11, 15]\n9", 'expected_output' => '[0, 1]', 'is_hidden' => '0'],
                ['input' => "[3,2,4]\n6", 'expected_output' => '[1,2]', 'is_hidden' => '1'],
            ],
        ];
    }

    private function makeProblem(User $user, array $over = []): Problem
    {
        $this->actingAs($user)->post(route('problems.store'), $this->payload($over))->assertRedirect()->assertSessionHasNoErrors();

        return Problem::query()->latest('id')->firstOrFail();
    }

    private function events($response): array
    {
        $response->assertOk();

        return collect(explode("\n", trim($response->streamedContent())))->filter()->map(fn ($l) => json_decode($l, true))->values()->all();
    }

    private function require(string $lang): void
    {
        if (! app(LanguageRuntimeProbe::class)->isAvailable($lang)) {
            $this->markTestSkipped("{$lang} not installed");
        }
    }

    public function test_stores_signature_and_canonical_json_tests(): void
    {
        $problem = $this->makeProblem(User::factory()->create());

        $this->assertTrue($problem->isFunction());
        $this->assertSame('twoSum', $problem->functionSignature()->name);
        $this->assertSame("[2,7,11,15]\n9", $problem->testCases[0]->input);
        $this->assertSame('[0,1]', $problem->testCases[0]->expected_output);
        $this->assertSame('exact', $problem->compareMode());
    }

    public function test_test_values_must_match_the_signature(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('problems.store'), $this->payload(['tests' => [
            ['input' => "[1,2]\n\"9\"", 'expected_output' => '[0,1]'],   // target is a string
            ['input' => '[1,2]', 'expected_output' => '[0,1]'],          // missing argument
            ['input' => "[1,2]\n3", 'expected_output' => '1'],           // wrong return type
        ]]))->assertSessionHasErrors(['tests.0.input', 'tests.1.input', 'tests.2.expected_output']);
        $this->assertDatabaseCount('problems', 0);
    }

    public function test_signature_names_are_validated(): void
    {
        $user = User::factory()->create();
        $bad = fn ($sig) => $this->actingAs($user)->post(route('problems.store'), $this->payload(['signature' => $sig]));

        $bad(['name' => 'class', 'params' => [], 'returns' => 'int'])->assertSessionHasErrors('signature.name');
        $bad(['name' => '9lives', 'params' => [], 'returns' => 'int'])->assertSessionHasErrors('signature.name');
        $bad(['name' => 'f', 'params' => [['name' => 'a', 'type' => 'int'], ['name' => 'a', 'type' => 'int']], 'returns' => 'int'])
            ->assertSessionHasErrors('signature.params.0.name');
        $bad(['name' => 'f', 'params' => [['name' => 'a', 'type' => 'map']], 'returns' => 'int'])->assertSessionHasErrors('signature.params.0.type');
    }

    public function test_zero_parameter_function(): void
    {
        $this->require('python');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user, [
            'signature' => ['name' => 'answer', 'returns' => 'int'],
            'tests' => [['input' => '', 'expected_output' => '42', 'is_hidden' => '0']],
        ]);

        $done = collect($this->events($this->actingAs($user)->post(route('problems.submit', $problem), [
            'language' => 'python', 'code' => "def answer():\n    return 42\n",
        ])))->last();
        $this->assertSame('accepted', $done['status']);
    }

    public function test_submit_accepts_in_each_language_and_separates_prints(): void
    {
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);
        $solutions = [
            'python' => "def twoSum(nums, target):\n    print('debug')\n    seen = {}\n    for i, n in enumerate(nums):\n        if target - n in seen:\n            return [seen[target - n], i]\n        seen[n] = i\n",
            'javascript' => "function twoSum(nums, target) {\n  console.log('debug');\n  const m = new Map();\n  for (let i = 0; i < nums.length; i++) { if (m.has(target - nums[i])) return [m.get(target - nums[i]), i]; m.set(nums[i], i); }\n}\n",
            'php' => "<?php\nfunction twoSum(array \$nums, int \$target): array {\n  echo \"debug\\n\";\n  \$seen = [];\n  foreach (\$nums as \$i => \$n) { if (isset(\$seen[\$target - \$n])) return [\$seen[\$target - \$n], \$i]; \$seen[\$n] = \$i; }\n  return [];\n}\n",
        ];

        foreach ($solutions as $lang => $code) {
            if (! app(LanguageRuntimeProbe::class)->isAvailable($lang)) {
                continue;
            }
            $events = $this->events($this->actingAs($user)->post(route('problems.submit', $problem), ['language' => $lang, 'code' => $code]));
            $this->assertSame('accepted', end($events)['status'], $lang.': '.json_encode($events));
            $this->assertSame('[0,1]', $events[1]['actual'], $lang);
            $this->assertSame('debug', $events[1]['stdout'], $lang);
        }
    }

    public function test_wrong_answer_gets_a_hint_and_hidden_cases_stay_redacted(): void
    {
        $this->require('python');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $events = $this->events($this->actingAs($user)->post(route('problems.submit', $problem), [
            'language' => 'python', 'code' => "def twoSum(nums, target):\n    print(nums, target)\n    return [1, 0]\n",
        ]));

        $this->assertSame('wrong_answer', end($events)['status']);
        $this->assertSame('The right items, but in a different order.', $events[1]['hint']);
        foreach (['input', 'expected', 'actual', 'stderr', 'stdout', 'hint'] as $leaky) {
            $this->assertArrayNotHasKey($leaky, $events[2]);
        }
    }

    public function test_unordered_compare_mode(): void
    {
        $this->require('python');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user, ['compare_mode' => 'unordered']);

        $done = collect($this->events($this->actingAs($user)->post(route('problems.submit', $problem), [
            'language' => 'python',
            'code' => "def twoSum(nums, target):\n    for i in range(len(nums)):\n        for j in range(i + 1, len(nums)):\n            if nums[i] + nums[j] == target:\n                return [j, i]\n",
        ])))->last();
        $this->assertSame('accepted', $done['status']);
    }

    public function test_forgotten_return_and_missing_function(): void
    {
        $this->require('python');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $events = $this->events($this->actingAs($user)->post(route('problems.run-examples', $problem), [
            'language' => 'python', 'code' => "def twoSum(nums, target):\n    x = 1\n",
        ]));
        $this->assertSame('wrong_answer', $events[1]['status']);
        $this->assertStringContainsString('forget to `return`', $events[1]['hint']);

        $events = $this->events($this->actingAs($user)->post(route('problems.run-examples', $problem), [
            'language' => 'python', 'code' => "def two_sum(nums, target):\n    return []\n",
        ]));
        $this->assertSame('runtime_error', $events[1]['status']);
        $this->assertStringContainsString('Function `twoSum` is not defined', $events[1]['stderr']);
    }

    public function test_custom_run_validates_arguments_and_returns_value(): void
    {
        $this->require('python');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);
        $code = "def twoSum(nums, target):\n    return [len(nums), target]\n";

        $this->actingAs($user)->postJson(route('problems.run', $problem), ['language' => 'python', 'code' => $code, 'stdin' => "[1,2]\n\"x\""])
            ->assertStatus(422)->assertJsonPath('error', 'invalid_input');

        $this->actingAs($user)->postJson(route('problems.run', $problem), ['language' => 'python', 'code' => $code, 'stdin' => "[1,2,3]\n5"])
            ->assertOk()->assertJsonPath('returned', '[3,5]');
    }

    public function test_reference_check_fills_return_values_from_unsaved_signature(): void
    {
        $this->require('python');
        $events = $this->events($this->actingAs(User::factory()->create())->post(route('problems.reference-check'), [
            'language' => 'python',
            'code' => "def add(a, b):\n    return a + b\n",
            'io_mode' => 'function',
            'signature' => ['name' => 'add', 'params' => [['name' => 'a', 'type' => 'int'], ['name' => 'b', 'type' => 'int']], 'returns' => 'int'],
            'tests' => [['input' => "1\n2", 'expected_output' => ''], ['input' => '1', 'expected_output' => '']],
        ]));

        $this->assertSame('3', $events[1]['actual']);
        $this->assertSame('invalid', $events[2]['status']);
    }

    public function test_pages_render_function_examples(): void
    {
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $this->actingAs($user)->get(route('problems.show', $problem))->assertOk()->assertSee('nums = [2,7,11,15]');
        $this->actingAs($user)->get(route('problems.create'))->assertOk()->assertSee('Input format');
    }

    /* ───────── Go ───────── */

    private function submitGo(User $user, Problem $problem, string $code, string $route = 'problems.submit'): array
    {
        return $this->events($this->actingAs($user)->post(route($route, $problem), ['language' => 'go', 'code' => $code]));
    }

    public function test_go_accepts_and_builds_once(): void
    {
        $this->require('go');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);
        $code = "package main\n\nimport \"fmt\"\n\nfunc twoSum(nums []int, target int) []int {\n\tfmt.Println(\"debug\")\n\tseen := map[int]int{}\n\tfor i, n := range nums {\n\t\tif j, ok := seen[target-n]; ok {\n\t\t\treturn []int{j, i}\n\t\t}\n\t\tseen[n] = i\n\t}\n\treturn nil\n}\n";

        $events = $this->submitGo($user, $problem, $code);

        $this->assertSame(['start', 'compile', 'case', 'case', 'done'], array_column($events, 'type'));
        $this->assertTrue($events[1]['ok']);
        $this->assertSame('accepted', end($events)['status']);
        $this->assertSame('[0,1]', $events[2]['actual']);
        $this->assertSame('debug', $events[2]['stdout']);
    }

    public function test_go_nil_slice_is_an_empty_list_and_package_clause_is_optional(): void
    {
        $this->require('go');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user, ['tests' => [['input' => "[1]\n5", 'expected_output' => '[]', 'is_hidden' => '0']]]);

        // LeetCode-style paste: no package clause, modern syntax (range over int, Go 1.22+).
        $events = $this->submitGo($user, $problem, "func twoSum(nums []int, target int) []int {\n\tfor i := range len(nums) {\n\t\t_ = i\n\t}\n\treturn nil\n}\n");

        $this->assertSame('accepted', end($events)['status'], json_encode($events));
        $this->assertSame('[]', $events[2]['actual']);
    }

    public function test_go_zero_parameter_function(): void
    {
        $this->require('go');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user, [
            'signature' => ['name' => 'answer', 'returns' => 'float'],
            'tests' => [['input' => '', 'expected_output' => '0.5', 'is_hidden' => '0']],
        ]);

        $events = $this->submitGo($user, $problem, "package main\n\nfunc answer() float64 { return 0.5 }\n");
        $this->assertSame('accepted', end($events)['status'], json_encode($events));
    }

    public function test_go_compile_errors_are_reported_once_with_friendly_messages(): void
    {
        $this->require('go');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $events = $this->submitGo($user, $problem, "package main\n\nfunc twoSum(nums []int64, target int) []int {\n\treturn nil\n}\n");
        $this->assertSame(['start', 'compile', 'done'], array_column($events, 'type'));
        $this->assertFalse($events[1]['ok']);
        $this->assertStringContainsString('func twoSum(nums []int, target int) []int', $events[1]['message']);
        $this->assertSame('compile_error', end($events)['status']);
        $this->assertDatabaseHas('problem_submissions', ['status' => 'compile_error']);

        $events = $this->submitGo($user, $problem, "package main\n\nfunc twoSum(nums []int, target int) []int {\n\tx := 1\n\treturn nil\n}\n", 'problems.run-examples');
        $this->assertStringContainsString('solution.go:4', $events[1]['output']);
        $this->assertStringContainsString('unused variables', $events[1]['message']);

        $events = $this->submitGo($user, $problem, "package main\n\nfunc main() {}\n\nfunc twoSum(nums []int, target int) []int { return nil }\n", 'problems.run-examples');
        $this->assertStringContainsString('Remove your `func main()`', $events[1]['message']);
    }

    public function test_go_panic_is_a_runtime_error_and_hidden_panics_stay_private(): void
    {
        $this->require('go');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $events = $this->submitGo($user, $problem, "package main\n\nfunc twoSum(nums []int, target int) []int {\n\treturn []int{nums[target]}\n}\n");

        $this->assertSame('runtime_error', $events[2]['status']);
        $this->assertStringContainsString('solution.go:4', $events[2]['stderr']);
        foreach (['input', 'expected', 'actual', 'stderr', 'stdout', 'hint'] as $leaky) {
            $this->assertArrayNotHasKey($leaky, $events[3]);
        }
    }

    public function test_go_time_limit_counts_running_not_building(): void
    {
        $this->require('go');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user, ['time_limit_ms' => 500]);

        $slow = "package main\n\nimport \"time\"\n\nfunc twoSum(nums []int, target int) []int {\n\ttime.Sleep(2 * time.Second)\n\treturn nil\n}\n";
        $this->assertSame('time_limit', $this->submitGo($user, $problem, $slow, 'problems.run-examples')[2]['status']);
    }

    public function test_go_custom_run_and_reference_check(): void
    {
        $this->require('go');
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $this->actingAs($user)->postJson(route('problems.run', $problem), [
            'language' => 'go', 'code' => "package main\n\nfunc twoSum(nums []int, target int) []int { return []int{len(nums), target} }\n", 'stdin' => "[1,2,3]\n5",
        ])->assertOk()->assertJsonPath('returned', '[3,5]');

        $events = $this->events($this->actingAs($user)->post(route('problems.reference-check'), [
            'language' => 'go', 'code' => "package main\n\nfunc add(a int, b int) int { return a + b }\n", 'io_mode' => 'function',
            'signature' => ['name' => 'add', 'params' => [['name' => 'a', 'type' => 'int'], ['name' => 'b', 'type' => 'int']], 'returns' => 'int'],
            'tests' => [['input' => "1\n2", 'expected_output' => '']],
        ]));
        $this->assertSame('3', $events[1]['actual']);
    }

    public function test_go_conflicting_function_names_just_disable_go(): void
    {
        $sig = FunctionSignature::fromArray(['name' => 'append', 'params' => [], 'returns' => 'int']);
        $this->assertNotNull($sig, 'stored signatures load even if a name is now reserved');
        $this->assertFalse($sig->supports('go'));
        $this->assertTrue($sig->supports('python'));
    }

    public function test_value_comparison_is_type_strict_with_float_tolerance(): void
    {
        $this->assertTrue(FunctionSignature::valuesEqual(0.3, 0.1 + 0.2, 'float'));
        $this->assertTrue(FunctionSignature::valuesEqual(2, 2.0, 'float'));
        $this->assertFalse(FunctionSignature::valuesEqual(1, true, 'int'));
        $this->assertFalse(FunctionSignature::valuesEqual(9, '9', 'int'));
        $this->assertFalse(FunctionSignature::valuesEqual(3, 3.0, 'int'));
        $this->assertTrue(FunctionSignature::valuesEqual([[1, 2], [3]], [[3], [1, 2]], 'int[][]', true));
        $this->assertFalse(FunctionSignature::valuesEqual([[1, 2], [3]], [[3], [1, 2]], 'int[][]'));
    }

    public function test_go_harness_compiles_for_every_type(): void
    {
        $this->require('go');
        $judge = app(\App\Services\ProblemJudge::class);
        $values = ['int' => '1', 'float' => '1.5', 'string' => '"a<b"', 'bool' => 'true'];
        $params = [];
        $input = [];
        foreach (FunctionSignature::TYPES as $i => $type) {
            $params[] = ['name' => 'p'.$i, 'type' => $type];
            $base = $values[str_replace('[]', '', $type)];
            $input[] = str_repeat('[', substr_count($type, '[]')).$base.str_repeat(']', substr_count($type, '[]'));
        }
        foreach (FunctionSignature::TYPES as $idx => $returns) {
            // Two parameters (a scalar and this type), echoing this one back.
            $sig = FunctionSignature::fromArray(['name' => 'f', 'params' => [['name' => 'n', 'type' => 'int'], ['name' => 'v', 'type' => $returns]], 'returns' => $returns]);
            $go = \App\Services\FunctionHarness::goType($returns);
            $code = "package main\n\nfunc f(n int, v {$go}) {$go} {\n\treturn v\n}\n";

            $r = $judge->runCustom('go', $code, "7\n".$input[$idx], $sig);
            $this->assertSame($input[$idx], $r['returned'] ?? null, "{$returns}: ".($r['stderr'] ?? ''));
        }
    }
}
