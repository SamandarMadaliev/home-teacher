<?php

namespace Tests\Feature;

use App\Models\Problem;
use App\Models\User;
use App\Services\LanguageRuntimeProbe;
use App\Services\ProblemJudge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProblemTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return $over + [
            'title' => 'Sum',
            'difficulty' => 'easy',
            'description' => 'Add two numbers.',
            'tests' => [
                ['input' => "1 2\n", 'expected_output' => '3', 'is_hidden' => '0'],
                ['input' => "5 5\n", 'expected_output' => '10', 'is_hidden' => '1'],
            ],
        ];
    }

    private function makeProblem(User $user): Problem
    {
        $this->actingAs($user)->post(route('problems.store'), $this->payload())->assertRedirect();

        return Problem::query()->firstOrFail();
    }

    public function test_create_stores_problem_and_test_cases(): void
    {
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $this->assertSame($user->id, $problem->user_id);
        $this->assertSame([false, true], $problem->testCases->pluck('is_hidden')->all());
    }

    public function test_update_replaces_test_cases(): void
    {
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $this->actingAs($user)->put(route('problems.update', $problem), $this->payload([
            'tests' => [['input' => '', 'expected_output' => 'x']],
        ]))->assertRedirect();

        $this->assertCount(1, $problem->fresh()->testCases);
    }

    public function test_requires_at_least_one_test_case(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('problems.store'), $this->payload(['tests' => []]))
            ->assertSessionHasErrors('tests');
    }

    public function test_other_users_cannot_see_problem(): void
    {
        $problem = $this->makeProblem(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('problems.show', $problem))->assertNotFound();
    }

    public function test_owner_can_view_and_delete(): void
    {
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $this->actingAs($user)->get(route('problems.show', $problem))->assertOk()->assertSee('Sum');
        $this->actingAs($user)->delete(route('problems.destroy', $problem))->assertRedirect(route('problems.index'));
        $this->assertDatabaseCount('problems', 0);
        $this->assertDatabaseCount('problem_test_cases', 0);
    }

    public function test_output_normalization_ignores_trailing_whitespace(): void
    {
        $this->assertSame(ProblemJudge::normalize("a  \r\nb\n\n"), ProblemJudge::normalize("a\nb"));
        $this->assertNotSame(ProblemJudge::normalize('a b'), ProblemJudge::normalize('ab'));
    }

    /** @return array<int, array<string, mixed>> */
    private function events($response): array
    {
        $response->assertOk();

        return collect(explode("\n", trim($response->streamedContent())))
            ->filter()->map(fn ($l) => json_decode($l, true))->values()->all();
    }

    private function requirePython(): void
    {
        if (! app(LanguageRuntimeProbe::class)->isAvailable('python')) {
            $this->markTestSkipped('python3 not installed');
        }
    }

    public function test_submit_streams_verdict_and_hides_hidden_case_details(): void
    {
        $this->requirePython();
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);
        $good = "a, b = map(int, input().split())\nprint(a + b)\n";

        $events = $this->events($this->actingAs($user)->post(route('problems.submit', $problem), ['language' => 'python', 'code' => $good]));

        $this->assertSame(['start', 'case', 'case', 'done'], array_column($events, 'type'));
        $done = end($events);
        $this->assertSame('accepted', $done['status']);
        $this->assertSame(2, $done['passed']);
        $this->assertTrue($done['first_solve']);
        $this->assertSame(1, $done['submission']['attempt']);

        $hiddenCase = $events[2];
        $this->assertTrue($hiddenCase['hidden']);
        foreach (['input', 'expected', 'actual', 'stderr'] as $leaky) {
            $this->assertArrayNotHasKey($leaky, $hiddenCase);
        }

        $this->assertDatabaseCount('problem_submissions', 1);
        $this->assertCount(2, $problem->submissions()->first()->results);
        $this->assertTrue($problem->isSolved());

        // A second accepted submission is no longer a "first solve".
        $again = $this->events($this->actingAs($user)->post(route('problems.submit', $problem), ['language' => 'python', 'code' => $good]));
        $this->assertFalse(end($again)['first_solve']);
    }

    public function test_hidden_case_never_leaks_stderr_even_on_runtime_error(): void
    {
        $this->requirePython();
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $code = "import sys\nsys.stderr.write(sys.stdin.read())\nsys.exit(3)\n";
        $events = $this->events($this->actingAs($user)->post(route('problems.submit', $problem), ['language' => 'python', 'code' => $code]));

        $this->assertSame('runtime_error', end($events)['status']);
        $this->assertArrayNotHasKey('stderr', $events[2]);
        $this->assertStringNotContainsString('5 5', json_encode($events[2]));
    }

    public function test_wrong_answer_is_recorded_and_visible_case_has_details(): void
    {
        $this->requirePython();
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $events = $this->events($this->actingAs($user)->post(route('problems.submit', $problem), ['language' => 'python', 'code' => "print(0)\n"]));
        $done = end($events);

        $this->assertSame('wrong_answer', $done['status']);
        $this->assertFalse($done['first_solve']);
        $this->assertSame('3', $events[1]['expected']);
        $this->assertSame("0\n", $events[1]['actual']);
        $this->assertFalse($problem->isSolved());
    }

    public function test_run_examples_only_runs_visible_cases_and_records_nothing(): void
    {
        $this->requirePython();
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $events = $this->events($this->actingAs($user)->post(route('problems.run-examples', $problem), [
            'language' => 'python', 'code' => "a, b = map(int, input().split())\nprint(a + b)\n",
        ]));

        $this->assertSame(['start', 'case', 'done'], array_column($events, 'type'));
        $this->assertSame(1, $events[1]['number']);
        $this->assertFalse(end($events)['recorded']);
        $this->assertDatabaseCount('problem_submissions', 0);
    }

    public function test_reference_check_reports_per_case_results(): void
    {
        $this->requirePython();
        $user = User::factory()->create();

        $events = $this->events($this->actingAs($user)->post(route('problems.reference-check'), [
            'language' => 'python',
            'code' => "a, b = map(int, input().split())\nprint(a + b)\n",
            'tests' => [
                ['input' => '1 2', 'expected_output' => '3'],
                ['input' => '2 2', 'expected_output' => '5'],
                ['input' => '4 4', 'expected_output' => ''],
            ],
        ]));

        $cases = array_values(array_filter($events, fn ($e) => $e['type'] === 'case'));
        $this->assertSame([true, false, false], array_column($cases, 'passed'));
        $this->assertSame("4\n", $cases[1]['actual']);
        $this->assertSame("8\n", $cases[2]['actual']);
    }

    public function test_other_users_cannot_use_run_submit_or_read_submissions(): void
    {
        $owner = User::factory()->create();
        $problem = $this->makeProblem($owner);
        $submission = $problem->submissions()->create([
            'user_id' => $owner->id, 'language' => 'python', 'code' => 'x', 'status' => 'wrong_answer',
            'passed_count' => 0, 'total_count' => 2, 'duration_ms' => 1,
        ]);
        $other = User::factory()->create();
        $payload = ['language' => 'python', 'code' => 'print(1)'];

        $this->actingAs($other)->post(route('problems.submit', $problem), $payload)->assertNotFound();
        $this->actingAs($other)->post(route('problems.run-examples', $problem), $payload)->assertNotFound();
        $this->actingAs($other)->post(route('problems.run', $problem), $payload)->assertNotFound();
        $this->actingAs($other)->getJson(route('problems.submissions.show', [$problem, $submission]))->assertNotFound();
        $this->actingAs($owner)->getJson(route('problems.submissions.show', [$problem, $submission]))->assertOk()->assertJson(['code' => 'x']);
    }

    public function test_submission_must_belong_to_the_problem_in_the_url(): void
    {
        $user = User::factory()->create();
        $a = $this->makeProblem($user);
        $this->actingAs($user)->post(route('problems.store'), $this->payload(['title' => 'Other']));
        $b = Problem::query()->where('title', 'Other')->firstOrFail();
        $sub = $a->submissions()->create([
            'user_id' => $user->id, 'language' => 'python', 'code' => 'x', 'status' => 'accepted',
            'passed_count' => 2, 'total_count' => 2, 'duration_ms' => 1,
        ]);

        $this->actingAs($user)->getJson(route('problems.submissions.show', [$b, $sub]))->assertNotFound();
    }

    public function test_update_keeps_test_case_ids_and_removes_dropped_ones(): void
    {
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);
        [$first, $second] = $problem->testCases->all();

        $this->actingAs($user)->put(route('problems.update', $problem), $this->payload([
            'tests' => [
                ['id' => $second->id, 'input' => '9', 'expected_output' => '9', 'is_hidden' => '0'],
                ['input' => '1', 'expected_output' => '1', 'is_hidden' => '1'],
            ],
        ]))->assertRedirect(route('problems.show', $problem));

        $fresh = $problem->fresh()->testCases;
        $this->assertCount(2, $fresh);
        $this->assertSame($second->id, $fresh[0]->id);
        $this->assertSame('9', $fresh[0]->expected_output);
        $this->assertDatabaseMissing('problem_test_cases', ['id' => $first->id]);
    }

    public function test_cannot_adopt_another_problems_test_case_id(): void
    {
        $user = User::factory()->create();
        $a = $this->makeProblem($user);
        $this->actingAs($user)->post(route('problems.store'), $this->payload(['title' => 'B']));
        $b = Problem::query()->where('title', 'B')->firstOrFail();
        $foreign = $b->testCases->first();

        $this->actingAs($user)->put(route('problems.update', $a), $this->payload([
            'tests' => [['id' => $foreign->id, 'input' => 'z', 'expected_output' => 'z']],
        ]));

        $this->assertSame('3', $b->fresh()->testCases->first()->expected_output);
        $this->assertSame($b->id, $foreign->fresh()->problem_id);
    }

    public function test_authoring_fields_are_normalised_and_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('problems.store'), $this->payload([
            'tags' => ['Arrays', ' arrays ', 'DP', ''],
            'hints' => ['  first  ', '', 'second'],
            'reference_solution' => "print(1)\n",
            'reference_language' => 'python',
            'starter_code' => ['python' => 'x = 1', 'cobol' => 'nope', 'go' => '   '],
            'time_limit_ms' => 2000,
            'compare_mode' => 'tokens',
        ]))->assertRedirect();

        $p = Problem::query()->firstOrFail();
        $this->assertSame(['arrays', 'dp'], $p->tags);
        $this->assertSame(['first', 'second'], $p->hints);
        $this->assertSame(['python' => 'x = 1'], $p->starter_code);
        $this->assertSame(2000, $p->time_limit_ms);
        $this->assertSame('tokens', $p->compare_mode);
        $this->assertSame('python', $p->reference_language);
    }

    public function test_defaults_apply_when_new_fields_are_omitted_and_limits_are_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('problems.store'), $this->payload())->assertRedirect();
        $p = Problem::query()->firstOrFail();
        $this->assertSame(Problem::DEFAULT_TIME_LIMIT_MS, $p->time_limit_ms);
        $this->assertSame('trim_lines', $p->compare_mode);

        $this->actingAs($user)->post(route('problems.store'), $this->payload(['time_limit_ms' => 99999]))->assertSessionHasErrors('time_limit_ms');
        $this->actingAs($user)->post(route('problems.store'), $this->payload(['compare_mode' => 'nope']))->assertSessionHasErrors('compare_mode');
    }

    public function test_save_and_keep_editing_redirects_to_the_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('problems.store'), $this->payload() + ['continue' => '1']);
        $problem = Problem::query()->firstOrFail();

        $this->actingAs($user)->put(route('problems.update', $problem), $this->payload() + ['continue' => '1'])
            ->assertRedirect(route('problems.edit', $problem));
    }

    public function test_preview_renders_markdown_and_escapes_html(): void
    {
        $res = $this->actingAs(User::factory()->create())
            ->postJson(route('problems.preview'), ['body' => '**bold** <script>alert(1)</script>'])->assertOk();

        $this->assertStringContainsString('<strong>bold</strong>', $res->json('html'));
        $this->assertStringNotContainsString('<script>', $res->json('html'));
    }

    public function test_index_filters_by_search_status_difficulty_and_tag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('problems.store'), $this->payload(['title' => 'Alpha sum', 'tags' => ['math']]));
        $this->actingAs($user)->post(route('problems.store'), $this->payload(['title' => 'Beta graph', 'difficulty' => 'hard', 'tags' => ['graphs']]));
        $alpha = Problem::query()->where('title', 'Alpha sum')->firstOrFail();
        $alpha->submissions()->create([
            'user_id' => $user->id, 'language' => 'python', 'code' => 'x', 'status' => 'accepted',
            'passed_count' => 2, 'total_count' => 2, 'duration_ms' => 1,
        ]);

        $this->actingAs($user)->get(route('problems.index', ['q' => 'graph']))->assertOk()->assertSee('Beta graph')->assertDontSee('Alpha sum');
        $this->actingAs($user)->get(route('problems.index', ['status' => 'solved']))->assertSee('Alpha sum')->assertDontSee('Beta graph');
        $this->actingAs($user)->get(route('problems.index', ['status' => 'todo']))->assertSee('Beta graph')->assertDontSee('Alpha sum');
        $this->actingAs($user)->get(route('problems.index', ['difficulty' => 'hard']))->assertSee('Beta graph')->assertDontSee('Alpha sum');
        $this->actingAs($user)->get(route('problems.index', ['tag' => 'math']))->assertSee('Alpha sum')->assertDontSee('Beta graph');
    }

    public function test_index_does_not_list_other_users_problems(): void
    {
        $this->makeProblem(User::factory()->create());

        $this->actingAs(User::factory()->create())->get(route('problems.index'))->assertOk()->assertDontSee('Sum')->assertSee('No problems yet');
    }

    public function test_random_goes_to_an_unsolved_problem(): void
    {
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $this->actingAs($user)->get(route('problems.random'))->assertRedirect(route('problems.show', $problem));

        $problem->submissions()->create([
            'user_id' => $user->id, 'language' => 'python', 'code' => 'x', 'status' => 'accepted',
            'passed_count' => 2, 'total_count' => 2, 'duration_ms' => 1,
        ]);
        $this->actingAs($user)->get(route('problems.random'))->assertRedirect(route('problems.index'));
    }

    public function test_solve_and_form_pages_render(): void
    {
        $user = User::factory()->create();
        $problem = $this->makeProblem($user);

        $this->actingAs($user)->get(route('problems.show', $problem))->assertOk()->assertSee('Example 1')->assertSee('1 hidden test');
        $this->actingAs($user)->get(route('problems.create'))->assertOk()->assertSee('Reference solution');
        $this->actingAs($user)->get(route('problems.edit', $problem))->assertOk()->assertSee('Danger zone');
    }

    public function test_statement_html_is_escaped_on_the_solve_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('problems.store'), $this->payload(['description' => '<script>alert(1)</script>']));
        $problem = Problem::query()->firstOrFail();

        $this->actingAs($user)->get(route('problems.show', $problem))->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_compare_modes(): void
    {
        $this->assertTrue(ProblemJudge::matches("a  \nb\n\n", "a\nb", Problem::COMPARE_TRIM_LINES));
        $this->assertFalse(ProblemJudge::matches('a', "a\n", Problem::COMPARE_EXACT));
        $this->assertTrue(ProblemJudge::matches("1 2\n3", "1\n2 3\n", Problem::COMPARE_TOKENS));
        $this->assertFalse(ProblemJudge::matches('1 2', '12', Problem::COMPARE_TOKENS));
        $this->assertTrue(ProblemJudge::matches('0.3333333', '0.3333334', Problem::COMPARE_FLOAT));
        $this->assertFalse(ProblemJudge::matches('0.33', '0.34', Problem::COMPARE_FLOAT));
        $this->assertFalse(ProblemJudge::matches('abc 1', 'abd 1', Problem::COMPARE_FLOAT));
    }
}
