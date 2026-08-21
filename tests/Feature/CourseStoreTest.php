<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_title_defaults_to_folder_name_when_omitted(): void
    {
        $user = User::factory()->create();
        $dir = sys_get_temp_dir().'/ht-course-name-'.uniqid('', true);
        mkdir($dir, 0777, true);

        $this->actingAs($user)
            ->post(route('courses.store'), [
                'folder_path' => $dir,
            ])
            ->assertRedirect();

        $course = Course::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($course);
        $this->assertSame(basename(realpath($dir)), $course->title);
    }

    public function test_course_title_uses_provided_name(): void
    {
        $user = User::factory()->create();
        $dir = sys_get_temp_dir().'/ht-course-name-'.uniqid('', true);
        mkdir($dir, 0777, true);

        $this->actingAs($user)
            ->post(route('courses.store'), [
                'title' => 'Custom Title',
                'folder_path' => $dir,
            ])
            ->assertRedirect();

        $course = Course::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($course);
        $this->assertSame('Custom Title', $course->title);
    }

    public function test_folder_path_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('courses.store'), [
                'title' => 'No folder',
            ])
            ->assertSessionHasErrors('folder_path');
    }
}
