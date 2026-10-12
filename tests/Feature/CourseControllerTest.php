<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private function admin()
    {
        return $this->actingAs($this->makeAdmin());
    }

    public function test_index_lists_courses(): void
    {
        $course = Course::factory()->create(['name' => 'Basis Data Lanjut']);

        $this->admin()->get(route('admin.courses.index'))
            ->assertOk()
            ->assertViewIs('admin.courses.index')
            ->assertSee('Basis Data Lanjut');

        $this->assertEqualsCanonicalizing(
            [$course->id],
            Course::pluck('id')->all()
        );
    }

    public function test_index_shows_empty_state_without_courses(): void
    {
        $this->admin()->get(route('admin.courses.index'))->assertOk();
    }

    public function test_store_creates_a_course(): void
    {
        $this->admin()->post(route('admin.courses.store'), [
            'code' => 'IF-2101',
            'name' => 'Jaringan Komputer',
        ])->assertRedirect(route('admin.courses.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('courses', [
            'code' => 'IF-2101',
            'name' => 'Jaringan Komputer',
        ]);
    }

    public function test_store_requires_code_and_name(): void
    {
        $this->admin()->post(route('admin.courses.store'), [])
            ->assertSessionHasErrors(['code', 'name']);

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        Course::factory()->create(['code' => 'IF-2101']);

        $this->admin()->post(route('admin.courses.store'), [
            'code' => 'IF-2101',
            'name' => 'Duplikat',
        ])->assertSessionHasErrors('code');

        $this->assertDatabaseCount('courses', 1);
    }

    public function test_store_rejects_name_longer_than_255_characters(): void
    {
        $this->admin()->post(route('admin.courses.store'), [
            'code' => 'IF-9999',
            'name' => str_repeat('a', 256),
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_destroy_deletes_a_course_without_dependencies(): void
    {
        $course = Course::factory()->create();

        $this->admin()->delete(route('admin.courses.destroy', $course))
            ->assertRedirect(route('admin.courses.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_destroy_is_blocked_when_course_has_modules(): void
    {
        $course = Course::factory()->create();
        Module::factory()->create(['course_id' => $course->id]);

        $this->admin()->delete(route('admin.courses.destroy', $course))
            ->assertRedirect(route('admin.courses.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_destroy_is_blocked_when_course_has_exams(): void
    {
        $course = Course::factory()->create();
        Exam::factory()->create(['course_id' => $course->id]);

        $this->admin()->delete(route('admin.courses.destroy', $course))
            ->assertRedirect(route('admin.courses.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_guests_cannot_manage_courses(): void
    {
        $course = Course::factory()->create();

        $this->get(route('admin.courses.index'))->assertRedirect(route('login'));
        $this->post(route('admin.courses.store'), ['code' => 'X', 'name' => 'Y'])->assertRedirect(route('login'));
        $this->delete(route('admin.courses.destroy', $course))->assertRedirect(route('login'));

        $this->assertDatabaseCount('courses', 1);
    }

    public function test_students_cannot_manage_courses(): void
    {
        $course = Course::factory()->create();
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('admin.courses.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.courses.store'), ['code' => 'X', 'name' => 'Y'])->assertForbidden();
        $this->actingAs($student)->delete(route('admin.courses.destroy', $course))->assertForbidden();

        $this->assertDatabaseCount('courses', 1);
    }
}
