<?php

namespace Tests\Feature;

use App\Models\Problem;
use App\Models\ProblemSubmission;
use App\Models\User;
use App\Services\LanguageRuntimeProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProblemSharingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Problem $problem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->problem = $this->makeProblem($this->owner);
    }

    private function makeProblem(User $user, array $attrs = []): Problem
    {
        $problem = Problem::create($attrs + [
            'user_id' => $user->id,
            'title' => 'Sum',
            'difficulty' => 'easy',
            'description' => 'Add two numbers.',
            'reference_solution' => "print(SECRET_REFERENCE)\n",
            'reference_language' => 'python',
        ]);
        $problem->testCases()->create(['input' => '1 2', 'expected_output' => '3', 'is_hidden' => false, 'sort_order' => 0]);
        $problem->testCases()->create(['input' => 'HIDDEN_INPUT', 'expected_output' => 'HIDDEN_EXPECTED', 'is_hidden' => true, 'sort_order' => 1]);

        return $problem;
    }

    private function share(User $with, bool $includeSolution = false): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('problems.shares.store', $this->problem), ['email' => $with->email, 'include_solution' => $includeSolution])
            ->assertOk();
    }

    private function submission(User $user, string $status = 'accepted', array $attrs = []): ProblemSubmission
    {
        return $this->problem->submissions()->create($attrs + [
            'user_id' => $user->id,
            'language' => 'python',
            'code' => "# code by {$user->name}\n",
            'status' => $status,
            'passed_count' => 2,
            'total_count' => 2,
            'duration_ms' => 10,
            'problem_revision' => $this->problem->revision,
        ]);
    }

    private function makePublic(): void
    {
        $this->actingAs($this->owner)->postJson(route('problems.visibility', $this->problem), ['visibility' => 'public'])->assertOk();
        $this->problem->refresh();
    }

    // ───── Access ─────

    public function test_private_problem_is_404_for_everyone_else(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other);

        $this->get(route('problems.show', $this->problem))->assertNotFound();
        $this->postJson(route('problems.run', $this->problem), ['language' => 'python', 'code' => 'print(1)'])->assertNotFound();
        $this->postJson(route('problems.submit', $this->problem), ['language' => 'python', 'code' => 'print(1)'])->assertNotFound();
        $this->getJson(route('problems.solutions.index', $this->problem))->assertNotFound();
        $this->getJson(route('problems.solutions.reference', $this->problem))->assertNotFound();
    }

    public function test_sharing_by_email_gives_solve_access_but_not_edit_access(): void
    {
        $friend = User::factory()->create(['email' => 'friend@example.com']);
        $this->actingAs($this->owner)
            ->postJson(route('problems.shares.store', $this->problem), ['email' => '  FRIEND@example.com '])
            ->assertOk()
            ->assertJsonPath('shares.0.name', $friend->name);

        $this->actingAs($friend)->get(route('problems.show', $this->problem))
            ->assertOk()
            ->assertDontSee('HIDDEN_INPUT')
            ->assertDontSee('HIDDEN_EXPECTED')
            ->assertDontSee(route('problems.edit', $this->problem));
        $this->assertNotNull($this->problem->shares()->first()->seen_at);

        $this->get(route('problems.edit', $this->problem))->assertNotFound();
        $this->put(route('problems.update', $this->problem), ['title' => 'Hacked'])->assertNotFound();
        $this->delete(route('problems.destroy', $this->problem))->assertNotFound();
        $this->postJson(route('problems.visibility', $this->problem), ['visibility' => 'public'])->assertNotFound();
        $this->postJson(route('problems.shares.store', $this->problem), ['email' => $this->owner->email])->assertNotFound();
        $this->assertSame('Sum', $this->problem->fresh()->title);
    }

    public function test_unknown_email_self_and_duplicates_are_rejected(): void
    {
        $this->actingAs($this->owner);

        $this->postJson(route('problems.shares.store', $this->problem), ['email' => 'nobody@example.com'])
            ->assertStatus(422)->assertJsonPath('error', 'not_registered')
            ->assertJsonPath('message', 'This person needs to register first. Once they have an account, share the problem again.');
        $this->postJson(route('problems.shares.store', $this->problem), ['email' => $this->owner->email])
            ->assertStatus(422)->assertJsonPath('error', 'self');

        $friend = User::factory()->create();
        $this->share($friend);
        $this->postJson(route('problems.shares.store', $this->problem), ['email' => $friend->email])
            ->assertStatus(422)->assertJsonPath('error', 'already_shared');
        $this->assertSame(1, $this->problem->shares()->count());
    }

    public function test_a_fifth_person_needs_confirmation_and_makes_the_problem_public(): void
    {
        foreach (User::factory()->count(4)->create() as $u) {
            $this->share($u);
        }
        $fifth = User::factory()->create();

        $this->actingAs($this->owner)
            ->postJson(route('problems.shares.store', $this->problem), ['email' => $fifth->email])
            ->assertStatus(409)->assertJsonPath('error', 'limit');
        $this->assertSame('private', $this->problem->fresh()->visibility);

        $this->postJson(route('problems.shares.store', $this->problem), ['email' => $fifth->email, 'make_public' => true])
            ->assertOk()->assertJsonPath('visibility', 'public');
        $this->assertSame(5, $this->problem->shares()->count());
        $this->assertNotNull($this->problem->fresh()->published_at);

        // Back to "specific people" needs ≤ 4; "Only me" clears everyone.
        $this->postJson(route('problems.visibility', $this->problem), ['visibility' => 'private'])
            ->assertStatus(422)->assertJsonPath('error', 'too_many');
        $this->postJson(route('problems.visibility', $this->problem), ['visibility' => 'private', 'clear_shares' => true])
            ->assertOk()->assertJsonPath('visibility', 'private');
        $this->assertSame(0, $this->problem->shares()->count());
    }

    public function test_removing_access_keeps_their_submissions(): void
    {
        $friend = User::factory()->create();
        $this->share($friend);
        $this->submission($friend);

        $share = $this->problem->shares()->first();
        $this->actingAs($this->owner)->deleteJson(route('problems.shares.destroy', [$this->problem, $share]))->assertOk();

        $this->actingAs($friend)->get(route('problems.show', $this->problem))->assertNotFound();
        $this->assertSame(1, $this->problem->submissionsBy($friend)->count());
    }

    public function test_only_the_owner_can_change_a_share(): void
    {
        $friend = User::factory()->create();
        $this->share($friend);
        $share = $this->problem->shares()->first();
        $otherProblem = $this->makeProblem($this->owner, ['title' => 'Other']);

        $this->actingAs($friend)->patchJson(route('problems.shares.update', [$this->problem, $share]), ['include_solution' => true])->assertNotFound();
        // A share id from another problem in the URL doesn't work either.
        $this->actingAs($this->owner)->deleteJson(route('problems.shares.destroy', [$otherProblem, $share]))->assertNotFound();
        $this->assertFalse($share->fresh()->include_solution);
    }

    public function test_public_problems_are_open_to_every_member(): void
    {
        $this->makePublic();
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('problems.show', $this->problem))->assertOk()->assertSee('by Owner');
        $this->get(route('problems.index', ['scope' => 'community']))->assertOk()->assertSee('Sum');
        $this->get(route('problems.edit', $this->problem))->assertNotFound();
    }

    public function test_index_scopes(): void
    {
        $member = User::factory()->create();
        $this->makeProblem($member, ['title' => 'Mine only']);
        $this->share($member);

        $this->actingAs($member);
        $this->get(route('problems.index'))->assertSee('Mine only')->assertDontSee('>Sum<', false);
        $this->get(route('problems.index', ['scope' => 'shared']))->assertSee('Sum')->assertSee('1 new')->assertDontSee('Mine only');
        $this->get(route('problems.index', ['scope' => 'community']))->assertDontSee('Mine only');
    }

    // ───── Per-person submissions ─────

    public function test_submissions_stay_private_to_each_person(): void
    {
        $friend = User::factory()->create();
        $this->share($friend);
        $theirs = $this->submission($friend, attrs: ['code' => 'FRIENDS_CODE']);

        $this->actingAs($this->owner);
        $this->assertFalse($this->problem->isSolvedBy($this->owner));
        $this->get(route('problems.show', $this->problem))->assertOk()->assertDontSee('FRIENDS_CODE')->assertSee('0 attempts');
        // Not even the owner can read it through the submissions endpoint.
        $this->getJson(route('problems.submissions.show', [$this->problem, $theirs]))->assertNotFound();

        $this->actingAs($friend)->getJson(route('problems.submissions.show', [$this->problem, $theirs]))
            ->assertOk()->assertJsonPath('code', 'FRIENDS_CODE');
    }

    public function test_a_shared_user_can_submit_and_it_is_recorded_as_theirs(): void
    {
        if (! app(LanguageRuntimeProbe::class)->isAvailable('python')) {
            $this->markTestSkipped('python3 not installed');
        }
        $friend = User::factory()->create();
        $this->share($friend);

        $res = $this->actingAs($friend)->post(route('problems.submit', $this->problem), ['language' => 'python', 'code' => "print(0)\n"]);
        $res->assertOk();
        $this->assertStringNotContainsString('HIDDEN_EXPECTED', $res->streamedContent());

        $sub = ProblemSubmission::query()->sole();
        $this->assertSame($friend->id, $sub->user_id);
        $this->assertSame($this->problem->revision, $sub->problem_revision);
    }

    public function test_one_run_at_a_time_per_person(): void
    {
        $lock = Cache::lock('problem-judge:'.$this->owner->id, 60);
        $this->assertTrue($lock->get());

        $this->actingAs($this->owner)
            ->postJson(route('problems.submit', $this->problem), ['language' => 'python', 'code' => 'print(1)'])
            ->assertStatus(429)->assertJsonPath('error', 'busy');

        $lock->release();
    }

    // ───── Solutions ─────

    public function test_solutions_unlock_only_after_solving(): void
    {
        $this->makePublic();
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $this->submission($alice);

        $this->actingAs($bob)->getJson(route('problems.solutions.index', $this->problem))
            ->assertForbidden()->assertJsonPath('error', 'locked');
        $aliceSub = $this->problem->submissionsBy($alice)->first();
        $this->getJson(route('problems.solutions.show', [$this->problem, $aliceSub]))->assertNotFound();

        $this->submission($bob, 'wrong_answer');
        $this->getJson(route('problems.solutions.index', $this->problem))->assertForbidden();

        $this->submission($bob);
        $this->getJson(route('problems.solutions.index', $this->problem))
            ->assertOk()->assertJsonPath('access', 'all')->assertJsonCount(2, 'solutions');
        $this->getJson(route('problems.solutions.show', [$this->problem, $aliceSub]))
            ->assertOk()->assertJsonPath('code', "# code by Alice\n")->assertJsonMissingPath('results');
    }

    public function test_only_the_latest_visible_accepted_submission_per_language_is_listed(): void
    {
        $this->makePublic();
        $alice = User::factory()->create();
        $old = $this->submission($alice, attrs: ['code' => 'OLD']);
        $new = $this->submission($alice, attrs: ['code' => 'NEW']);
        $this->submission($alice, attrs: ['language' => 'javascript']);
        $this->submission($alice, 'wrong_answer', ['code' => 'BROKEN']);

        $ids = collect($this->actingAs($this->owner)->getJson(route('problems.solutions.index', $this->problem))->json('solutions'))->pluck('id');
        $this->assertCount(2, $ids);
        $this->assertTrue($ids->contains($new->id));
        $this->assertFalse($ids->contains($old->id));

        // Hiding the newest falls back to the previous one.
        $this->actingAs($alice)->postJson(route('problems.submissions.hide', [$this->problem, $new]), ['hidden' => true])->assertOk();
        $ids = collect($this->actingAs($this->owner)->getJson(route('problems.solutions.index', $this->problem))->json('solutions'))->pluck('id');
        $this->assertTrue($ids->contains($old->id));
        $this->assertFalse($ids->contains($new->id));
    }

    public function test_people_can_only_hide_their_own_submissions(): void
    {
        $this->makePublic();
        $alice = User::factory()->create();
        $sub = $this->submission($alice);

        $this->actingAs($this->owner)->postJson(route('problems.submissions.hide', [$this->problem, $sub]), ['hidden' => true])->assertNotFound();
        $this->assertNull($sub->fresh()->hidden_at);

        $this->actingAs($alice)->postJson(route('problems.solutions.hide-all', $this->problem))->assertOk()->assertJsonPath('hidden', 1);
        $this->assertNotNull($sub->fresh()->hidden_at);
    }

    public function test_include_solution_shows_only_the_owners_solutions_before_solving(): void
    {
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $this->share($friend, includeSolution: true);
        $this->share($stranger);
        $ownerSub = $this->submission($this->owner);
        $this->submission($stranger);

        $this->actingAs($friend);
        $res = $this->getJson(route('problems.solutions.index', $this->problem))->assertOk()->assertJsonPath('access', 'owner');
        $this->assertSame([$ownerSub->id], collect($res->json('solutions'))->pluck('id')->all());
        $this->assertNotNull($res->json('reference'));
        $this->getJson(route('problems.solutions.reference', $this->problem))->assertOk()->assertJsonPath('code', "print(SECRET_REFERENCE)\n");

        // Without include_solution: locked, and no reference.
        $this->actingAs($stranger);
        $this->problem->submissions()->where('user_id', $stranger->id)->delete();
        $this->getJson(route('problems.solutions.index', $this->problem))->assertForbidden();
        $this->getJson(route('problems.solutions.reference', $this->problem))->assertNotFound();
    }

    public function test_reference_solution_is_never_in_the_page_and_follows_its_setting(): void
    {
        $this->makePublic();
        $member = User::factory()->create();
        $this->submission($member);

        $this->actingAs($member)->get(route('problems.show', $this->problem))->assertOk()->assertDontSee('SECRET_REFERENCE');
        $this->getJson(route('problems.solutions.reference', $this->problem))->assertNotFound();

        $this->problem->update(['show_reference_solution' => true]);
        $this->getJson(route('problems.solutions.reference', $this->problem))->assertOk();

        $this->actingAs($this->owner)->get(route('problems.show', $this->problem))->assertDontSee('SECRET_REFERENCE');
    }

    // ───── Revisions ─────

    public function test_revision_goes_up_only_when_judging_changes(): void
    {
        $payload = fn (array $tests, string $title = 'Sum') => [
            'title' => $title, 'difficulty' => 'easy', 'description' => 'Add two numbers.', 'tests' => $tests,
        ];
        $tests = $this->problem->testCases->map(fn ($t) => [
            'id' => $t->id, 'input' => $t->input, 'expected_output' => $t->expected_output, 'is_hidden' => $t->is_hidden ? '1' : '0',
        ])->all();

        $this->actingAs($this->owner);
        $this->put(route('problems.update', $this->problem), $payload($tests, 'Renamed'))->assertRedirect();
        $this->assertSame(1, $this->problem->fresh()->revision);

        $sub = $this->submission($this->owner);
        $tests[1]['expected_output'] = 'CHANGED';
        $this->put(route('problems.update', $this->problem), $payload($tests))->assertRedirect();
        $this->assertSame(2, $this->problem->fresh()->revision);

        $this->getJson(route('problems.submissions.show', [$this->problem, $sub]))->assertJsonPath('outdated', true);
    }

    // ───── Moderation ─────

    public function test_admins_can_hide_public_problems_and_solutions_but_not_open_private_ones(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $member = User::factory()->create();
        $friend = User::factory()->create();
        $this->share($friend);

        $this->actingAs($admin)->get(route('problems.show', $this->problem))->assertNotFound();
        $this->postJson(route('problems.moderate', $this->problem), ['hidden' => true])->assertNotFound();

        $this->makePublic();
        $sub = $this->submission($member);
        // Admins can review community solutions without solving.
        $this->actingAs($admin)->getJson(route('problems.solutions.index', $this->problem))->assertOk();
        $this->postJson(route('problems.solutions.moderate', [$this->problem, $sub]), ['hidden' => true])->assertOk();
        $this->actingAs($this->owner)->getJson(route('problems.solutions.index', $this->problem))->assertJsonCount(0, 'solutions');

        $this->actingAs($admin)->postJson(route('problems.moderate', $this->problem), ['hidden' => true])->assertOk();
        $this->actingAs(User::factory()->create())->get(route('problems.show', $this->problem))->assertNotFound();
        $this->actingAs($friend)->get(route('problems.show', $this->problem))->assertOk();
        $this->actingAs($this->owner)->get(route('problems.show', $this->problem))->assertOk()->assertSee('An admin has hidden this problem');

        // Non-admins can't moderate.
        $this->actingAs($member)->postJson(route('problems.moderate', $this->problem), ['hidden' => false])->assertForbidden();
    }

    public function test_problem_pages_block_remote_images(): void
    {
        $this->actingAs($this->owner)->get(route('problems.show', $this->problem))
            ->assertHeader('Content-Security-Policy', "img-src 'self' data:");
    }
}
