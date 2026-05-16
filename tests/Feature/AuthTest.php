<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_home_and_auth_pages(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }

    public function test_guest_is_redirected_from_courses(): void
    {
        $this->get(route('courses.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_register_and_sign_in(): void
    {
        $this->post(route('register'), [
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs(User::query()->where('email', 'sam@example.com')->first());

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login'), [
            'email' => 'sam@example.com',
            'password' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated();
    }

    public function test_first_registered_user_claims_legacy_courses(): void
    {
        $orphan = Course::query()->create([
            'title' => 'Legacy',
            'folder_path' => null,
        ]);

        $this->post(route('register'), [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'owner@example.com')->first();
        $this->assertSame($user->id, $orphan->fresh()->user_id);
    }

    public function test_users_only_see_their_own_courses(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Course::query()->create([
            'user_id' => $alice->id,
            'title' => 'Alice course',
        ]);

        Course::query()->create([
            'user_id' => $bob->id,
            'title' => 'Bob course',
        ]);

        $this->actingAs($alice)
            ->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Alice course')
            ->assertDontSee('Bob course');
    }

    public function test_user_cannot_open_another_users_course(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $bobsCourse = Course::query()->create([
            'user_id' => $bob->id,
            'title' => 'Private',
        ]);

        $this->actingAs($alice)
            ->get(route('courses.show', $bobsCourse))
            ->assertNotFound();
    }
}
