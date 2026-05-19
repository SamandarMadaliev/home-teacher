<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserAccentColor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_use_default_blue_accent(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-accent="blue"', false);
    }

    public function test_authenticated_user_can_update_accent_color(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('profile.accent.update'), [
                'accent_color' => 'red',
            ])
            ->assertOk()
            ->assertJson(['accent_color' => 'red']);

        $this->assertSame('red', $user->fresh()->accent_color);
    }

    public function test_blue_accent_is_stored_as_null(): void
    {
        $user = User::factory()->create(['accent_color' => 'green']);

        $this->actingAs($user)
            ->patchJson(route('profile.accent.update'), [
                'accent_color' => 'blue',
            ])
            ->assertOk()
            ->assertJson(['accent_color' => 'blue']);

        $this->assertNull($user->fresh()->accent_color);
    }

    public function test_invalid_accent_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('profile.accent.update'), [
                'accent_color' => 'purple',
            ])
            ->assertUnprocessable();
    }

    public function test_user_page_renders_their_accent_on_html_element(): void
    {
        $user = User::factory()->create(['accent_color' => 'orange']);

        $this->actingAs($user)
            ->get(route('courses.index'))
            ->assertOk()
            ->assertSee('data-accent="orange"', false)
            ->assertSee('data-accent-value="orange"', false);
    }

    public function test_other_user_keeps_default_accent(): void
    {
        $accentUser = User::factory()->create(['accent_color' => 'red']);
        $defaultUser = User::factory()->create();

        $this->actingAs($defaultUser)
            ->get(route('courses.index'))
            ->assertOk()
            ->assertSee('data-accent="blue"', false)
            ->assertDontSee('data-accent="red"', false);

        $this->actingAs($accentUser)
            ->get(route('courses.index'))
            ->assertOk()
            ->assertSee('data-accent="red"', false);
    }

    public function test_all_allowed_accents_are_valid(): void
    {
        $user = User::factory()->create();

        foreach (UserAccentColor::ALLOWED as $accent) {
            $this->actingAs($user)
                ->patchJson(route('profile.accent.update'), [
                    'accent_color' => $accent,
                ])
                ->assertOk()
                ->assertJson(['accent_color' => $accent]);
        }
    }
}
