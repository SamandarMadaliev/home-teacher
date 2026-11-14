<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use App\Models\Course;
use App\Models\Invite;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAttachment;
use App\Support\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/** Admin-only disk access, invite-only sign-up and related hardening. */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function signup(array $over = [])
    {
        return $this->post(route('register'), $over + [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
        ]);
    }

    private function invite(User $by, array $attrs = []): Invite
    {
        return $by->invites()->create($attrs + ['token' => Invite::newToken()]);
    }

    /* ───────── Admin role ───────── */

    public function test_the_first_account_becomes_admin_even_when_sign_up_is_invite_only(): void
    {
        config(['registration.mode' => 'invite']);

        $this->signup()->assertRedirect(route('home'));

        $this->assertTrue(User::query()->where('email', 'new@example.com')->value('is_admin'));
    }

    public function test_is_admin_cannot_be_mass_assigned(): void
    {
        $user = User::create(['name' => 'x', 'email' => 'x@example.com', 'password' => 'secret', 'is_admin' => true]);

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_non_admins_cannot_reach_disk_features(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['user_id' => $user->id, 'title' => 'Mine', 'folder_path' => sys_get_temp_dir()]);

        $this->actingAs($user)->get(route('courses.create'))->assertRedirect(route('courses.index'));
        $this->actingAs($user)->post(route('courses.store'), ['folder_path' => sys_get_temp_dir()])->assertRedirect(route('courses.index'));
        $this->actingAs($user)->post(route('courses.rescan', $course))->assertRedirect(route('courses.index'));
        $this->actingAs($user)->getJson(route('folder-picker'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.index'))->assertRedirect(route('courses.index'));
        $this->assertSame(1, Course::query()->count());
    }

    public function test_non_admins_can_add_links_but_not_files_by_path(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['user_id' => $user->id, 'title' => 'C', 'folder_path' => sys_get_temp_dir()]);
        $video = Video::create(['course_id' => $course->id, 'title' => 'L', 'file_path' => 'x.mp4', 'type' => 'video', 'sort_order' => 0]);

        $this->actingAs($user)->post(route('videos.attachments.store', $video), ['kind' => 'file', 'file_path' => base_path('.env')])
            ->assertSessionHasErrors('file_path');
        $this->actingAs($user)->post(route('videos.attachments.store', $video), ['kind' => 'link', 'url' => 'https://example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['link'], VideoAttachment::query()->pluck('kind')->all());
    }

    public function test_absolute_path_attachments_outside_the_course_folder_only_work_for_admins(): void
    {
        $folder = sys_get_temp_dir().'/ht-course-'.uniqid();
        mkdir($folder);
        $outside = tempnam(sys_get_temp_dir(), 'ht-secret');

        foreach ([false, true] as $admin) {
            $owner = $admin ? User::factory()->admin()->create() : User::factory()->create();
            $course = Course::create(['user_id' => $owner->id, 'title' => 'C', 'folder_path' => $folder]);
            $video = Video::create(['course_id' => $course->id, 'title' => 'L', 'file_path' => 'x.mp4', 'type' => 'video', 'sort_order' => 0]);
            // Created directly, like attachments that existed before this rule.
            $attachment = VideoAttachment::create(['video_id' => $video->id, 'kind' => 'file', 'title' => 'secret', 'file_path' => $outside, 'sort_order' => 0]);

            $response = $this->actingAs($owner)->get(route('videos.attachments.download', [$video, $attachment]));
            $admin ? $response->assertOk() : $response->assertNotFound();
        }
    }

    public function test_admins_can_add_courses_only_inside_allowed_roots(): void
    {
        $admin = User::factory()->admin()->create();
        $allowed = sys_get_temp_dir().'/ht-roots-'.uniqid();
        mkdir($allowed.'/inside', 0777, true);
        config(['course.browse_roots' => $allowed]);

        $this->actingAs($admin)->post(route('courses.store'), ['folder_path' => $allowed.'/inside'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('courses.store'), ['folder_path' => base_path()])->assertSessionHasErrors('folder_path');
    }

    public function test_admin_can_manage_admins_but_never_remove_the_last_one(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee($other->email);
        $this->actingAs($admin)->post(route('admin.users.toggle-admin', $other))->assertRedirect();
        $this->assertTrue($other->fresh()->isAdmin());

        $this->actingAs($admin)->post(route('admin.users.toggle-admin', $other));
        $this->actingAs($admin)->post(route('admin.users.toggle-admin', $admin))->assertSessionHasErrors('users');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_make_admin_command(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->artisan('app:make-admin', ['email' => 'me@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());
        $this->artisan('app:make-admin', ['email' => 'me@example.com', '--remove' => true])->assertFailed(); // last admin
        $this->artisan('app:make-admin', ['email' => 'nobody@example.com'])->assertFailed();
    }

    /* ───────── Invite-only sign-up ───────── */

    public function test_sign_up_without_an_invite_is_refused(): void
    {
        User::factory()->admin()->create();

        $this->get(route('register'))->assertOk()->assertSee('An invitation is needed')->assertDontSee('name="password"', false);
        $this->signup()->assertRedirect(route('register'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_sign_up_through_an_invite_link(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Samandar']);
        $invite = $this->invite($admin, ['max_uses' => 1, 'note' => 'study group']);

        $this->get(route('invites.accept', $invite->token))->assertRedirect(route('register'));
        $this->get(route('register'))->assertSee('Samandar')->assertSee('invited you');
        $this->signup()->assertRedirect(route('home'));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertSame($admin->id, $user->invited_by_id);
        $this->assertFalse($user->isAdmin());
        $this->assertSame(1, $invite->fresh()->uses);
        $this->assertSame('used', $invite->fresh()->status());
    }

    public function test_a_single_use_invite_cannot_be_used_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $invite = $this->invite($admin, ['max_uses' => 1]);

        $this->get(route('invites.accept', $invite->token));
        $this->signup()->assertRedirect(route('home'));
        auth()->logout();

        $this->get(route('invites.accept', $invite->token))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->withSession([Registration::SESSION_KEY => $invite->token])->signup(['email' => 'second@example.com'])->assertRedirect(route('register'));
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_expired_and_revoked_invites_do_not_work(): void
    {
        $admin = User::factory()->admin()->create();
        $expired = $this->invite($admin, ['expires_at' => now()->subMinute()]);
        $revoked = $this->invite($admin);
        $revoked->forceFill(['revoked_at' => now()])->save();

        foreach ([$expired, $revoked] as $invite) {
            $this->get(route('invites.accept', $invite->token))->assertRedirect(route('login'));
        }
        $this->get(route('invites.accept', 'not-a-real-token'))->assertRedirect(route('login'));
    }

    public function test_open_and_closed_modes(): void
    {
        User::factory()->admin()->create();

        config(['registration.mode' => 'open']);
        $this->signup()->assertRedirect(route('home'));
        auth()->logout();

        config(['registration.mode' => 'closed']);
        $invite = $this->invite(User::query()->first());
        $this->get(route('invites.accept', $invite->token));
        $this->signup(['email' => 'closed@example.com'])->assertRedirect(route('register'));
        $this->assertDatabaseMissing('users', ['email' => 'closed@example.com']);
    }

    public function test_google_sign_up_needs_an_invite_too(): void
    {
        $admin = User::factory()->admin()->create();
        $this->mockGoogle('g-1', 'google@example.com', verified: true);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'google@example.com']);

        $invite = $this->invite($admin);
        $this->get(route('invites.accept', $invite->token));
        $this->get(route('auth.google.callback'))->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'google@example.com', 'invited_by_id' => $admin->id]);
    }

    public function test_google_only_links_existing_accounts_with_a_verified_email(): void
    {
        $existing = User::factory()->create(['email' => 'victim@example.com']);
        $this->mockGoogle('attacker', 'victim@example.com', verified: false);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($existing->fresh()->google_id);
    }

    private function mockGoogle(string $id, string $email, bool $verified): void
    {
        $user = (new GoogleUser)->setRaw(['email_verified' => $verified])->map(['id' => $id, 'name' => 'G User', 'email' => $email]);
        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    /* ───────── Invites page ───────── */

    public function test_members_can_create_and_revoke_their_own_invites_within_limits(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->post(route('invites.store'), ['max_uses' => '1', 'expires_in_days' => '7'])->assertRedirect(route('invites.index'));
        $this->actingAs($member)->post(route('invites.store'), ['max_uses' => 'unlimited', 'expires_in_days' => '7'])->assertSessionHasErrors('max_uses');
        $this->actingAs($member)->post(route('invites.store'), ['max_uses' => '1', 'expires_in_days' => 'never'])->assertSessionHasErrors('expires_in_days');

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($member)->post(route('invites.store'), ['max_uses' => '1', 'expires_in_days' => '1']);
        }
        $this->actingAs($member)->post(route('invites.store'), ['max_uses' => '1', 'expires_in_days' => '1'])->assertSessionHasErrors('max_uses');
        $this->assertSame(5, $member->invites()->count());

        $mine = $member->invites()->first();
        $this->actingAs($member)->get(route('invites.index'))->assertOk()->assertSee($mine->url());
        $this->actingAs($member)->delete(route('invites.destroy', $mine))->assertRedirect();
        $this->assertSame('revoked', $mine->fresh()->status());

        $theirs = $this->invite(User::factory()->create());
        $this->actingAs($member)->delete(route('invites.destroy', $theirs))->assertNotFound();
        $this->actingAs($member)->get(route('invites.index'))->assertDontSee($theirs->token);
    }

    /* ───────── Proxies, cookies, check ───────── */

    public function test_forwarded_ip_is_only_believed_from_trusted_proxies(): void
    {
        config(['app.trusted_proxies' => '127.0.0.1']);
        $ipFrom = function (string $remote) {
            $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => '6.6.6.6']);

            return (new TrustProxies)->handle($request, fn ($r) => response($r->ip()))->getContent();
        };

        $this->assertSame('6.6.6.6', $ipFrom('127.0.0.1'));
        $this->assertSame('203.0.113.9', $ipFrom('203.0.113.9'));
    }

    public function test_session_cookie_is_secure_over_https(): void
    {
        $this->get('https://localhost/login')->assertOk();
        $this->assertTrue(config('session.secure'));
    }

    public function test_security_check_command_flags_unsafe_settings(): void
    {
        User::factory()->admin()->create();
        config(['app.debug' => true, 'sandbox.driver' => 'none']);

        $this->artisan('app:security-check')->assertFailed();
    }
}
