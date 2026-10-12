<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Auth guards write to the database session store by default.
        config(['session.driver' => 'array']);
    }

    public function test_login_page_renders_for_guests(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertViewIs('auth.login');
    }

    public function test_login_with_valid_admin_credentials_redirects_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->withPassword('secret123')->create();

        $this->post('/login', [
            'username' => $admin->username,
            'password' => 'secret123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_with_valid_student_credentials_redirects_to_student_dashboard(): void
    {
        $student = User::factory()->student()->withPassword('secret123')->create();

        $this->post('/login', [
            'username' => $student->username,
            'password' => 'secret123',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_login_uses_username_not_email(): void
    {
        $admin = User::factory()->admin()->withPassword('secret123')->create();

        // The email exists in the DB but must not be usable as a login field.
        $this->post('/login', [
            'username' => $admin->email,
            'password' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password_and_keeps_username_input(): void
    {
        $student = User::factory()->student()->withPassword('correct-password')->create();

        $this->post('/login', [
            'username' => $student->username,
            'password' => 'wrong-password',
        ])
            ->assertSessionHasErrors('username')
            ->assertSessionHasInput('username', $student->username);

        $this->assertGuest();
    }

    public function test_login_requires_username_and_password(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors(['username', 'password']);

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $student = User::factory()->student()->withPassword('correct-password')->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => $student->username,
                'password' => 'wrong-password',
            ]);
        }

        // Sixth attempt is blocked by the throttle:5,1 middleware.
        $this->post('/login', [
            'username' => $student->username,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    public function test_correct_credentials_still_work_when_within_the_throttle_limit(): void
    {
        $student = User::factory()->student()->withPassword('correct-password')->create();

        $this->post('/login', [
            'username' => $student->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->post('/login', [
            'username' => $student->username,
            'password' => 'correct-password',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_login_page_redirects_authenticated_admin_to_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_page_redirects_authenticated_student_to_dashboard(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get('/login')
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_logout_logs_the_user_out_and_redirects_to_login(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_logout_regenerates_the_csrf_token(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student);

        $before = session()->token();
        $this->post('/logout');
        $after = session()->token();

        $this->assertNotSame($before, $after);
    }

    public function test_root_route_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_admin_can_reach_admin_dashboard_after_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_session_is_regenerated_on_login_to_prevent_fixation(): void
    {
        $admin = User::factory()->admin()->withPassword('secret123')->create();

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', [
            'username' => $admin->username,
            'password' => 'secret123',
        ]);

        $this->assertNotSame($before, session()->getId());
    }
}
