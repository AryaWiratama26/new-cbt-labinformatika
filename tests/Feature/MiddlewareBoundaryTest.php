<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\StudentMiddleware;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class MiddlewareBoundaryTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    /* ------------------------------------------------------------------
     | AdminMiddleware
     | ----------------------------------------------------------------- */

    public function test_admin_middleware_lets_admin_through(): void
    {
        // The middleware resolves the user through the auth guard, so the account
        // must be logged in rather than injected into the request.
        $this->actingAs($this->makeAdmin());

        $response = (new AdminMiddleware)->handle(Request::create('/admin/dashboard', 'GET'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_admin_middleware_rejects_student(): void
    {
        $this->actingAs($this->makeStudent());
        $request = Request::create('/admin/dashboard', 'GET');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Unauthorized access.');

        (new AdminMiddleware)->handle($request, fn () => response('ok'));
    }

    public function test_admin_middleware_rejects_guest_without_user(): void
    {
        $request = Request::create('/admin/dashboard', 'GET');

        $this->expectException(HttpException::class);

        (new AdminMiddleware)->handle($request, fn () => response('ok'));
    }

    public function test_admin_middleware_returns_403_status(): void
    {
        $this->actingAs($this->makeStudent());
        $request = Request::create('/admin/dashboard', 'GET');

        try {
            (new AdminMiddleware)->handle($request, fn () => response('ok'));
            $this->fail('Expected the middleware to abort with 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    /* ------------------------------------------------------------------
     | StudentMiddleware
     | ----------------------------------------------------------------- */

    public function test_student_middleware_lets_student_through(): void
    {
        $this->actingAs($this->makeStudent());

        $response = (new StudentMiddleware)->handle(Request::create('/student/dashboard', 'GET'), fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_student_middleware_rejects_admin(): void
    {
        $this->actingAs($this->makeAdmin());
        $request = Request::create('/student/dashboard', 'GET');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Akses ditolak.');

        (new StudentMiddleware)->handle($request, fn () => response('ok'));
    }

    /* ------------------------------------------------------------------
     | Route level wiring
     | ----------------------------------------------------------------- */

    public function test_guest_is_redirected_to_login_before_role_checks(): void
    {
        $course = Course::factory()->create();

        // auth middleware runs first, so a guest never reaches AdminMiddleware.
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.courses.modules.index', $course))->assertRedirect(route('login'));
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
    }

    public function test_student_is_forbidden_across_admin_routes(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.students.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.classrooms'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.exams.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.analytics'))->assertForbidden();
    }

    public function test_student_cannot_perform_admin_write_actions(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('admin.students.store'), [])->assertForbidden();
        $this->actingAs($student)->delete(route('admin.courses.destroy', Course::factory()->create()))->assertForbidden();
        $this->actingAs($student)->post(route('admin.classrooms.store'), [])->assertForbidden();
    }

    public function test_admin_is_forbidden_across_student_routes(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('student.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('student.history'))->assertForbidden();
    }

    public function test_admin_can_reach_every_role_protected_area(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.students.index'))->assertOk();
        $this->actingAs($admin)->get(route('student.dashboard'))->assertForbidden();
    }

    public function test_authenticated_roles_pass_the_correct_dashboard(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->makeStudent())->get(route('student.dashboard'))->assertOk();
    }
}
