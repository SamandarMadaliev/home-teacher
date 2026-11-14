<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LanguageRuntimeProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $password, string $ip = '10.0.0.1')
    {
        return $this->withHeader('X-Forwarded-For', $ip)
            ->post(route('login'), ['email' => $email, 'password' => $password]);
    }

    public function test_repeated_wrong_passwords_lock_the_account_briefly(): void
    {
        $user = User::factory()->create(['password' => bcrypt('right-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->login($user->email, 'wrong')->assertSessionHasErrors('email');
        }

        // Even the right password is refused while locked.
        $this->login($user->email, 'right-password')->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_spoofing_a_new_ip_per_attempt_does_not_bypass_the_limit(): void
    {
        $user = User::factory()->create(['password' => bcrypt('right-password')]);

        for ($i = 0; $i < 10; $i++) {
            $this->login($user->email, 'wrong', "203.0.113.{$i}");
        }

        $this->login($user->email, 'right-password', '198.51.100.7')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login_still_works_and_resets_the_counter(): void
    {
        $user = User::factory()->create(['password' => bcrypt('right-password')]);
        $this->login($user->email, 'wrong');

        $this->login($user->email, 'right-password')->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_playground_does_not_reveal_runtime_host_paths(): void
    {
        $paths = array_filter(array_column(app(LanguageRuntimeProbe::class)->detectList(), 'binary'));

        $html = $this->actingAs(User::factory()->create())->get(route('playground.show'))->assertOk()->getContent();

        foreach ($paths as $path) {
            $this->assertStringNotContainsString($path, $html);
        }
    }
}
