<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classroom = Classroom::factory()->create();
    }

    /**
     * Student enrolled in the shared classroom.
     */
    private function student()
    {
        return $this->makeStudent($this->classroom);
    }

    private function sessionKey(Exam $exam, $user): string
    {
        return 'exam_pin_'.$exam->id.'_'.$user->classroom_id;
    }

    // ---------------------------------------------------------------- dashboard

    public function test_dashboard_shows_available_exam_for_student_classroom(): void
    {
        [, $module] = $this->makeCourseWithModule(5);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);

        $student = $this->student();

        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $response->assertOk()->assertViewIs('student.dashboard');
        $response->assertViewHas('exams', function ($exams) use ($exam) {
            return $exams->count() === 1
                && $exams->first()->id === $exam->id
                && $exams->first()->status === 'available'
                && $exams->first()->questions_count === 5;
        });
    }

    public function test_dashboard_hides_exams_that_already_ended(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
        ]);

        $student = $this->student();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('exams', fn ($exams) => $exams->count() === 0);
    }

    public function test_dashboard_hides_exams_from_other_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $otherClass = Classroom::factory()->create();
        $this->makeExamWithSchedule($module, $otherClass, ['start_time' => now()]);

        $student = $this->student();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('exams', fn ($exams) => $exams->count() === 0);
    }

    public function test_dashboard_hides_exams_marked_inactive_for_the_classroom(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now(), 'is_active' => false,
        ]);

        $student = $this->student();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('exams', fn ($exams) => $exams->count() === 0);
    }

    public function test_dashboard_hides_exams_marked_inactive_globally(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['is_active' => false]);

        $student = $this->student();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('exams', fn ($exams) => $exams->count() === 0);
    }

    public function test_dashboard_marks_exam_as_waiting_before_start_time(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->addDay(),
            'end_time' => now()->addDays(2),
        ]);

        $student = $this->student();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('exams', fn ($exams) => $exams->first()->status === 'waiting');
    }

    public function test_dashboard_marks_unfinished_session_as_in_progress(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('exams', fn ($exams) => $exams->first()->status === 'in_progress');
    }

    public function test_dashboard_marks_finished_passed_exam_as_finished(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->finished(90)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('exams', fn ($exams) => $exams->first()->status === 'finished');
    }

    public function test_dashboard_marks_failed_exam_with_remaining_attempts_as_remedial(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_attempts' => 3, 'passing_grade' => 70]);
        $student = $this->student();

        ExamSession::factory()->finished(40)->attempt(1)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('exams', fn ($exams) => $exams->first()->status === 'remedial');
    }

    public function test_dashboard_marks_failed_exam_without_remaining_attempts_as_finished(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_attempts' => 2]);
        $student = $this->student();

        ExamSession::factory()->finished(40)->attempt(2)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertViewHas('exams', fn ($exams) => $exams->first()->status === 'finished');
    }

    // ---------------------------------------------------------------- show / PIN

    public function test_show_renders_prep_page_for_available_exam(): void
    {
        [, $module] = $this->makeCourseWithModule(3);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $this->actingAs($student)->get(route('student.exams.show', $exam))
            ->assertOk()
            ->assertViewIs('student.exams.show')
            ->assertViewHas('needsPin', false);
    }

    public function test_show_is_forbidden_for_students_of_other_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $otherClass = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $otherClass, ['start_time' => now()]);
        $student = $this->student();

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
    }

    public function test_show_is_forbidden_when_classroom_activation_is_disabled(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now(), 'is_active' => false,
        ]);
        $student = $this->student();

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
    }

    public function test_show_is_forbidden_after_the_exam_window_closed(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
        ]);
        $student = $this->student();

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
    }

    public function test_show_flags_remedial_and_unfinished_state(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_attempts' => 2]);
        $student = $this->student();

        ExamSession::factory()->finished(30)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))
            ->assertOk()
            ->assertViewHas('canRemedial', true)
            ->assertViewHas('hasUnfinished', false);
    }

    public function test_show_requires_pin_when_the_classroom_has_one(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now(), 'pin' => '4321',
        ]);
        $student = $this->student();

        $this->actingAs($student)->get(route('student.exams.show', $exam))
            ->assertOk()
            ->assertViewHas('needsPin', true);
    }

    public function test_verify_pin_accepts_the_correct_pin_and_stores_it_in_session(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now(), 'pin' => '4321',
        ]);
        $student = $this->student();

        $this->actingAs($student)
            ->post(route('student.exams.verify_pin', $exam), ['pin' => '4321'])
            ->assertRedirect(route('student.exams.show', $exam))
            ->assertSessionHas('success');

        $this->assertSame('4321', session($this->sessionKey($exam, $student)));

        $this->actingAs($student)->get(route('student.exams.show', $exam))
            ->assertViewHas('needsPin', false);
    }

    public function test_verify_pin_rejects_a_wrong_pin(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now(), 'pin' => '4321',
        ]);
        $student = $this->student();

        $this->actingAs($student)
            ->post(route('student.exams.verify_pin', $exam), ['pin' => '9999'])
            ->assertRedirect(route('student.exams.show', $exam))
            ->assertSessionHas('error')
            ->assertSessionHas('pin_error', true);

        $this->assertNull(session($this->sessionKey($exam, $student)));
    }

    public function test_verify_pin_validates_presence_of_pin_field(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now(), 'pin' => '4321',
        ]);
        $student = $this->student();

        $this->actingAs($student)
            ->post(route('student.exams.verify_pin', $exam), [])
            ->assertSessionHasErrors('pin');
    }

    public function test_verify_pin_is_throttled(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now(), 'pin' => '4321',
        ]);
        $student = $this->student();

        foreach (range(1, 5) as $ignored) {
            $this->actingAs($student)->post(route('student.exams.verify_pin', $exam), ['pin' => '0000']);
        }

        $this->actingAs($student)
            ->post(route('student.exams.verify_pin', $exam), ['pin' => '4321'])
            ->assertStatus(429);
    }

    public function test_verify_pin_is_forbidden_for_students_of_other_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $otherClass = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $otherClass, ['start_time' => now(), 'pin' => '4321']);
        $student = $this->student();

        $this->actingAs($student)
            ->post(route('student.exams.verify_pin', $exam), ['pin' => '4321'])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- start

    public function test_start_creates_a_session_and_redirects_to_attempt(): void
    {
        [, $module] = $this->makeCourseWithModule(3);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $this->actingAs($student)->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.attempt', $exam));

        $this->assertDatabaseHas('exam_sessions', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'attempt_number' => 1,
            'finished_at' => null,
        ]);
    }

    public function test_start_redirects_to_existing_unfinished_session_without_duplicating(): void
    {
        [, $module] = $this->makeCourseWithModule(3);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $existing = ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $this->actingAs($student)->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.attempt', $exam));

        $this->assertDatabaseCount('exam_sessions', 1);
        $this->assertSame($existing->id, ExamSession::first()->id);
    }

    public function test_start_is_blocked_before_the_window_opens(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->addHour(),
            'end_time' => now()->addDays(1),
        ]);
        $student = $this->student();

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();
        $this->assertDatabaseCount('exam_sessions', 0);
    }

    public function test_start_is_blocked_after_the_window_closes(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
        ]);
        $student = $this->student();

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();
    }

    public function test_start_requires_the_pin_first(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now(), 'pin' => '4321',
        ]);
        $student = $this->student();

        $this->actingAs($student)->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.show', $exam))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('exam_sessions', 0);
    }

    public function test_start_proceeds_once_the_pin_has_been_verified(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now(), 'pin' => '4321',
        ]);
        $student = $this->student();

        $this->actingAs($student)
            ->withSession([$this->sessionKey($exam, $student) => '4321'])
            ->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.attempt', $exam));

        $this->assertDatabaseCount('exam_sessions', 1);
    }

    public function test_start_refuses_when_the_exam_has_no_questions(): void
    {
        [, $module] = $this->makeCourseWithModule(0);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $this->actingAs($student)->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('exam_sessions', 0);
    }

    public function test_start_refuses_when_the_student_already_passed(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_attempts' => 3]);
        $student = $this->student();

        ExamSession::factory()->finished(95)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $this->actingAs($student)->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('exam_sessions', 1);
    }

    public function test_start_refuses_when_the_attempt_limit_is_reached(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_attempts' => 2]);
        $student = $this->student();

        ExamSession::factory()->finished(40)->attempt(2)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);

        $this->actingAs($student)->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('exam_sessions', 1);
    }

    public function test_start_allows_a_remedial_attempt_after_a_failing_score(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_attempts' => 2]);
        $student = $this->student();

        ExamSession::factory()->finished(40)->attempt(1)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);

        $this->actingAs($student)->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.attempt', $exam));

        $this->assertDatabaseHas('exam_sessions', [
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 2,
        ]);
    }

    public function test_start_is_forbidden_for_students_of_other_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $otherClass = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $otherClass, ['start_time' => now()]);
        $student = $this->student();

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertForbidden();
        $this->assertDatabaseCount('exam_sessions', 0);
    }

    // ---------------------------------------------------------------- attempt

    public function test_attempt_renders_the_exam_page_with_questions_and_options(): void
    {
        [, $module] = $this->makeCourseWithModule(3);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $response = $this->actingAs($student)->get(route('student.exams.attempt', $exam));

        $response->assertOk()
            ->assertViewIs('student.exams.attempt')
            ->assertViewHas('questions', fn ($questions) => $questions->count() === 3
                && $questions->every(fn ($q) => $q->options->count() === 4))
            ->assertViewHas('session', fn ($s) => $s->id === $session->id)
            ->assertViewHas('endTime');
    }

    public function test_attempt_shuffles_questions_deterministically_per_attempt(): void
    {
        [, $module] = $this->makeCourseWithModule(6);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $first = $this->actingAs($student)->get(route('student.exams.attempt', $exam))
            ->viewData('questions')->pluck('id')->all();
        $second = $this->actingAs($student)->get(route('student.exams.attempt', $exam))
            ->viewData('questions')->pluck('id')->all();

        $this->assertSame($first, $second, 'Reloading must keep the same order');
        $this->assertCount(6, $first);
    }

    public function test_attempt_requires_an_unfinished_session(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        // No session at all: must be sent to the prep page.
        $this->actingAs($student)->get(route('student.exams.attempt', $exam))
            ->assertRedirect(route('student.exams.show', $exam))
            ->assertSessionHas('error');
    }

    public function test_attempt_redirects_when_the_exam_was_already_submitted(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->finished(90)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->get(route('student.exams.attempt', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_attempt_auto_submits_when_the_duration_has_elapsed(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subHours(3),
            'end_time' => now()->addHours(3),
            'duration_minutes' => 30,
        ]);
        $student = $this->student();

        $session = ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'started_at' => now()->subHours(2),
        ]);
        $this->answerCorrectly($session, $module->questions()->first());

        $this->actingAs($student)->get(route('student.exams.attempt', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('success');

        $session->refresh();
        $this->assertNotNull($session->finished_at);
        $this->assertEquals(50.0, (float) $session->score);
    }

    public function test_attempt_auto_submits_when_the_tab_switch_limit_is_exceeded(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_tab_switches' => 2]);
        $student = $this->student();

        $session = ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'tab_switches' => 5,
        ]);

        $this->actingAs($student)->get(route('student.exams.attempt', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('success');

        $this->assertNotNull($session->fresh()->finished_at);
    }

    public function test_attempt_uses_the_earlier_of_duration_or_absolute_end_time(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(5),
            'duration_minutes' => 60,
        ]);
        $student = $this->student();

        ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'started_at' => now()->subMinutes(5),
        ]);

        // Duration (started_at + 60min) is earlier than the absolute end_time.
        $this->actingAs($student)->get(route('student.exams.attempt', $exam))
            ->assertOk()
            ->assertViewHas('endTime', function ($endTime) {
                return $endTime->isBefore(now()->addHours(5));
            });
    }

    // ---------------------------------------------------------------- saveAnswer

    public function test_save_answer_stores_the_selection(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $question = $module->questions()->first();
        $option = $question->options()->where('is_correct', false)->first();

        $this->actingAs($student)
            ->postJson(route('student.exams.save_answer', $exam), [
                'question_id' => $question->id,
                'option_id' => $option->id,
            ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('answers', [
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'option_id' => $option->id,
        ]);
    }

    public function test_save_answer_updates_an_existing_answer_instead_of_duplicating(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $question = $module->questions()->first();
        $first = $question->options()->where('is_correct', true)->first();
        $second = $question->options()->where('is_correct', false)->first();

        $this->answerCorrectly($session, $question);

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $question->id,
            'option_id' => $second->id,
        ])->assertOk();

        $this->assertDatabaseCount('answers', 1);
        $this->assertDatabaseHas('answers', ['option_id' => $second->id]);
    }

    public function test_save_answer_with_null_option_clears_the_answer(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $question = $module->questions()->first();
        $this->answerCorrectly($session, $question);

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $question->id,
            'option_id' => null,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseCount('answers', 0);
    }

    public function test_save_answer_rejects_a_question_from_another_module(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        [$otherCourse, $otherModule] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $foreignQuestion = $otherModule->questions()->first();

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $foreignQuestion->id,
            'option_id' => $foreignQuestion->options()->first()->id,
        ])->assertStatus(400)->assertJson(['success' => false]);

        $this->assertDatabaseCount('answers', 0);
    }

    public function test_save_answer_rejects_an_option_belonging_to_another_question(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $questions = $module->questions()->get();
        $optionFromOther = $questions[1]->options()->first();

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $questions[0]->id,
            'option_id' => $optionFromOther->id,
        ])->assertStatus(400)->assertJson(['success' => false]);

        $this->assertDatabaseCount('answers', 0);
    }

    public function test_save_answer_returns_400_without_an_active_session(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $question = $module->questions()->first();

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $question->id,
            'option_id' => $question->options()->first()->id,
        ])->assertStatus(400);
    }

    public function test_save_answer_is_forbidden_after_the_time_limit(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subHours(3),
            'end_time' => now()->addHours(3),
            'duration_minutes' => 30,
        ]);
        $student = $this->student();

        $session = ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'started_at' => now()->subHours(2),
        ]);
        $question = $module->questions()->first();

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $question->id,
            'option_id' => $question->options()->first()->id,
        ])->assertStatus(403);

        $this->assertDatabaseCount('answers', 0);
    }

    public function test_save_answer_is_forbidden_for_students_of_other_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $otherClass = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $otherClass, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $question = $module->questions()->first();

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $question->id,
            'option_id' => $question->options()->first()->id,
        ])->assertStatus(403);
    }

    // ---------------------------------------------------------------- tab switch

    public function test_report_tab_switch_increments_the_counter(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->postJson(route('student.exams.tab_switch', $exam))
            ->assertOk()
            ->assertJson(['success' => true, 'tab_switches' => 1, 'exceeded' => false]);

        $this->assertSame(1, $session->fresh()->tab_switches);
    }

    public function test_report_tab_switch_sets_semantics_never_rolls_back(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'tab_switches' => 7,
        ]);

        // A lower number from a stale offline client must not undo progress.
        $this->actingAs($student)->postJson(route('student.exams.tab_switch', $exam), ['total_switches' => 2])
            ->assertOk()
            ->assertJson(['tab_switches' => 7]);

        $this->assertSame(7, $session->fresh()->tab_switches);
    }

    public function test_report_tab_switch_raises_exceeded_once_past_the_limit(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_tab_switches' => 2]);
        $student = $this->student();

        ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'tab_switches' => 2,
        ]);

        $this->actingAs($student)->postJson(route('student.exams.tab_switch', $exam))
            ->assertOk()
            ->assertJson(['tab_switches' => 3, 'exceeded' => true]);
    }

    public function test_report_tab_switch_is_disabled_when_the_exam_sets_no_limits(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_tab_switches' => null, 'require_fullscreen' => false]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->postJson(route('student.exams.tab_switch', $exam))
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_report_tab_switch_works_when_only_fullscreen_is_required(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $exam->update(['max_tab_switches' => null, 'require_fullscreen' => true]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->postJson(route('student.exams.tab_switch', $exam))
            ->assertOk()
            ->assertJson(['success' => true, 'tab_switches' => 1]);

        $this->assertSame(1, $session->fresh()->tab_switches);
    }

    public function test_report_tab_switch_returns_400_without_an_active_session(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $this->actingAs($student)->postJson(route('student.exams.tab_switch', $exam))->assertStatus(400);
    }

    // ---------------------------------------------------------------- sync

    public function test_sync_bulk_saves_pending_offline_answers(): void
    {
        [, $module] = $this->makeCourseWithModule(3);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $questions = $module->questions()->get();

        $payload = [
            'answers' => $questions->map(fn ($q) => [
                'question_id' => $q->id,
                'option_id' => $q->options()->where('is_correct', true)->first()->id,
            ])->all(),
        ];

        $this->actingAs($student)
            ->postJson(route('student.exams.sync', $exam), $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'synced' => 3, 'errors' => []]);

        $this->assertDatabaseCount('answers', 3);
        $this->assertNotNull($session->id);
    }

    public function test_sync_keeps_only_the_latest_answer_per_question(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $question = $module->questions()->first();
        $options = $question->options()->get();

        $this->actingAs($student)->postJson(route('student.exams.sync', $exam), [
            'answers' => [
                ['question_id' => $question->id, 'option_id' => $options[0]->id],
                ['question_id' => $question->id, 'option_id' => $options[1]->id],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('answers', 1);
        $this->assertDatabaseHas('answers', ['option_id' => $options[1]->id]);
    }

    public function test_sync_reports_invalid_question_ids_without_aborting_the_batch(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        [$otherCourse, $otherModule] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $good = $module->questions()->first();
        $foreign = $otherModule->questions()->first();

        $response = $this->actingAs($student)->postJson(route('student.exams.sync', $exam), [
            'answers' => [
                ['question_id' => $good->id, 'option_id' => $good->options()->where('is_correct', true)->first()->id],
                ['question_id' => $foreign->id, 'option_id' => $foreign->options()->first()->id],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('synced', 1)
            ->assertJsonCount(1, 'errors');

        $this->assertDatabaseCount('answers', 1);
    }

    public function test_sync_reports_invalid_option_ids(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $questions = $module->questions()->get();
        $foreignOption = $questions[1]->options()->first();

        $this->actingAs($student)->postJson(route('student.exams.sync', $exam), [
            'answers' => [
                ['question_id' => $questions[0]->id, 'option_id' => $foreignOption->id],
            ],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('synced', 0)
            ->assertJsonCount(1, 'errors');

        $this->assertDatabaseCount('answers', 0);
    }

    public function test_sync_updates_tab_switches_with_set_semantics(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'tab_switches' => 9,
        ]);

        $this->actingAs($student)->postJson(route('student.exams.sync', $exam), ['tab_switches' => 3])
            ->assertOk()
            ->assertJson(['tab_switches' => 9]);

        $this->assertSame(9, $session->fresh()->tab_switches);
    }

    public function test_sync_accepts_an_empty_batch(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->postJson(route('student.exams.sync', $exam), [])
            ->assertOk()
            ->assertJson(['success' => true, 'synced' => 0]);
    }

    public function test_sync_returns_404_without_an_active_session(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $this->actingAs($student)->postJson(route('student.exams.sync', $exam), [])
            ->assertStatus(404);
    }

    public function test_sync_is_allowed_during_a_60_second_grace_window(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subHours(3),
            'end_time' => now()->addHours(3),
            'duration_minutes' => 30,
        ]);
        $student = $this->student();

        // Started 30m30s ago: just inside the grace window.
        ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'started_at' => now()->subSeconds(1830),
        ]);

        $question = $module->questions()->first();

        $this->actingAs($student)->postJson(route('student.exams.sync', $exam), [
            'answers' => [
                ['question_id' => $question->id, 'option_id' => $question->options()->where('is_correct', true)->first()->id],
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseCount('answers', 1);
    }

    public function test_sync_is_rejected_past_the_grace_window(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subHours(3),
            'end_time' => now()->addHours(3),
            'duration_minutes' => 30,
        ]);
        $student = $this->student();

        ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'started_at' => now()->subMinutes(35),
        ]);

        $response = $this->actingAs($student)->postJson(route('student.exams.sync', $exam), []);

        $response->assertStatus(403)->assertJson(['success' => false, 'expired' => true]);
    }

    public function test_sync_is_forbidden_for_students_of_other_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $otherClass = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $otherClass, ['start_time' => now()]);
        $student = $this->student();

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->postJson(route('student.exams.sync', $exam), [])->assertStatus(403);
    }

    // ---------------------------------------------------------------- submit

    public function test_submit_scores_from_stored_answers(): void
    {
        [, $module] = $this->makeCourseWithModule(4);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $questions = $module->questions()->get();

        // 2 of 4 correct.
        $this->answerCorrectly($session, $questions[0]);
        $this->answerCorrectly($session, $questions[1]);
        $this->answerIncorrectly($session, $questions[2]);

        $this->actingAs($student)->post(route('student.exams.submit', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('success');

        $session->refresh();
        $this->assertNotNull($session->finished_at);
        $this->assertEquals(50.0, (float) $session->score);
    }

    public function test_submit_ignores_a_score_injected_in_the_request_body(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $this->answerIncorrectly($session, $module->questions()->first());

        $this->actingAs($student)->post(route('student.exams.submit', $exam), [
            'score' => 100,
            'answers' => [],
        ])->assertRedirect(route('student.dashboard'));

        $this->assertEquals(0.0, (float) $session->fresh()->score);
    }

    public function test_submit_merges_form_answers_before_scoring(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $questions = $module->questions()->get();

        $this->actingAs($student)->post(route('student.exams.submit', $exam), [
            'answers' => [
                $questions[0]->id => $questions[0]->options()->where('is_correct', true)->first()->id,
                $questions[1]->id => $questions[1]->options()->where('is_correct', false)->first()->id,
            ],
        ])->assertRedirect(route('student.dashboard'));

        $this->assertEquals(50.0, (float) $session->fresh()->score);
    }

    public function test_submit_ignores_answers_for_questions_outside_the_exam(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        [, $otherModule] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $foreign = $otherModule->questions()->first();

        $this->actingAs($student)->post(route('student.exams.submit', $exam), [
            'answers' => [$foreign->id => $foreign->options()->where('is_correct', true)->first()->id],
        ])->assertRedirect(route('student.dashboard'));

        // The out-of-scope question must not create an answer row.
        $this->assertDatabaseCount('answers', 0);
        $this->assertEquals(0.0, (float) $session->fresh()->score);
    }

    public function test_submit_is_idempotent_when_no_session_is_open(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $this->actingAs($student)->post(route('student.exams.submit', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('exam_sessions', 0);
    }

    public function test_submit_auto_scores_after_the_grace_window(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subHours(3),
            'end_time' => now()->addHours(3),
            'duration_minutes' => 30,
        ]);
        $student = $this->student();

        $session = ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'started_at' => now()->subHours(2),
        ]);
        $this->answerCorrectly($session, $module->questions()->first());

        $this->actingAs($student)->post(route('student.exams.submit', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('success');

        $this->assertEquals(50.0, (float) $session->fresh()->score);
    }

    public function test_submit_ignores_the_request_answers_once_past_the_grace_window(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, [
            'start_time' => now()->subHours(3),
            'end_time' => now()->addHours(3),
            'duration_minutes' => 30,
        ]);
        $student = $this->student();

        $session = ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'started_at' => now()->subHours(2),
        ]);

        $questions = $module->questions()->get();

        $this->actingAs($student)->post(route('student.exams.submit', $exam), [
            'answers' => [
                $questions[0]->id => $questions[0]->options()->where('is_correct', true)->first()->id,
                $questions[1]->id => $questions[1]->options()->where('is_correct', true)->first()->id,
            ],
        ])->assertRedirect(route('student.dashboard'));

        // Only what was already saved counts; late form data is discarded.
        $this->assertDatabaseCount('answers', 0);
        $this->assertEquals(0.0, (float) $session->fresh()->score);
    }

    public function test_submit_is_forbidden_for_students_of_other_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $otherClass = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $otherClass, ['start_time' => now()]);
        $student = $this->student();

        $this->actingAs($student)->post(route('student.exams.submit', $exam))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('error');
    }

    // ---------------------------------------------------------------- history

    public function test_history_lists_only_finished_sessions_newest_first(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $examA = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()->subDays(2)]);
        $examB = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()->subDay()]);
        $student = $this->student();

        ExamSession::factory()->finished(90)->create([
            'user_id' => $student->id, 'exam_id' => $examA->id,
            'finished_at' => now()->subDays(2),
        ]);
        ExamSession::factory()->finished(80)->create([
            'user_id' => $student->id, 'exam_id' => $examB->id,
            'finished_at' => now()->subDay(),
        ]);
        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $examA->id, 'attempt_number' => 2]);

        $response = $this->actingAs($student)->get(route('student.history'));

        $response->assertOk()
            ->assertViewIs('student.history.index')
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 2
                && $sessions->first()->exam_id === $examB->id);
    }

    public function test_history_does_not_leak_other_students_sessions(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();
        $other = $this->makeStudent($this->classroom);

        ExamSession::factory()->finished(90)->create(['user_id' => $other->id, 'exam_id' => $exam->id]);

        $this->actingAs($student)->get(route('student.history'))
            ->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 0);
    }

    public function test_review_session_shows_correctness_per_question(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->finished(50)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $questions = $module->questions()->get();
        $this->answerCorrectly($session, $questions[0]);
        $this->answerIncorrectly($session, $questions[1]);

        $this->actingAs($student)->get(route('student.history.review', $session))
            ->assertOk()
            ->assertViewIs('student.history.review')
            ->assertViewHas('correctCount', 1)
            ->assertViewHas('totalQuestions', 2);
    }

    public function test_review_session_is_forbidden_for_other_students(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();
        $other = $this->makeStudent($this->classroom);

        $session = ExamSession::factory()->finished(90)->create([
            'user_id' => $other->id, 'exam_id' => $exam->id,
        ]);

        $this->actingAs($student)->get(route('student.history.review', $session))->assertForbidden();
    }

    // ---------------------------------------------------------------- access

    public function test_students_cannot_reach_admin_routes(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.analytics'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.students.index'))->assertForbidden();
    }

    public function test_admins_cannot_reach_student_routes(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('student.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('student.history'))->assertForbidden();
    }

    public function test_guests_are_redirected_from_student_routes(): void
    {
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->get(route('student.history'))->assertRedirect(route('login'));
    }
}
