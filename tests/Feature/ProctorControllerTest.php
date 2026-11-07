<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ProctorEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class ProctorControllerTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classroom = Classroom::factory()->create();
    }

    private function cameraExam(): Exam
    {
        [, $module] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $this->classroom);
        $exam->update(['camera_proctoring' => true]);

        return $exam->fresh();
    }

    public function test_camera_exam_requires_server_side_camera_readiness(): void
    {
        $exam = $this->cameraExam();
        $student = $this->makeStudent($this->classroom);

        $this->actingAs($student)
            ->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.show', $exam));

        $this->assertDatabaseCount('exam_sessions', 0);

        $this->actingAs($student)
            ->post(route('proctor.camera_ready', $exam))
            ->assertOk()
            ->assertJson(['ready' => true]);

        $this->actingAs($student)
            ->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.attempt', $exam));

        $this->assertDatabaseHas('exam_sessions', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
        ]);
    }

    public function test_student_cannot_post_event_for_another_students_session(): void
    {
        $exam = $this->cameraExam();
        $student = $this->makeStudent($this->classroom);
        $otherStudent = $this->makeStudent($this->classroom);
        $otherSession = ExamSession::factory()->create([
            'user_id' => $otherStudent->id,
            'exam_id' => $exam->id,
        ]);

        $this->actingAs($student)
            ->postJson(route('proctor.event'), [
                'session_id' => $otherSession->id,
                'source' => 'laptop',
                'type' => 'no_face',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('proctor_events', 0);
    }

    public function test_student_can_post_event_for_own_active_session(): void
    {
        $exam = $this->cameraExam();
        $student = $this->makeStudent($this->classroom);
        $session = ExamSession::factory()->create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
        ]);

        $this->actingAs($student)
            ->postJson(route('proctor.event'), [
                'session_id' => $session->id,
                'source' => 'laptop',
                'type' => 'no_face',
                'meta' => ['count' => 0],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('proctor_events', [
            'exam_session_id' => $session->id,
            'source' => 'laptop',
            'type' => 'no_face',
        ]);
        $this->assertNotNull($session->fresh()->laptop_seen_at);
    }

    public function test_student_cannot_post_or_poll_signals_for_another_session(): void
    {
        $exam = $this->cameraExam();
        $student = $this->makeStudent($this->classroom);
        $otherStudent = $this->makeStudent($this->classroom);
        $otherSession = ExamSession::factory()->create([
            'user_id' => $otherStudent->id,
            'exam_id' => $exam->id,
        ]);

        $this->actingAs($student)
            ->postJson(route('proctor.signal'), [
                'session_id' => $otherSession->id,
                'channel' => 'laptop',
                'viewer_id' => 'viewer-1',
                'sender' => 'student',
                'type' => 'offer',
                'payload' => '{}',
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->getJson(route('proctor.signal.poll', [
                'session_id' => $otherSession->id,
                'viewer_id' => 'viewer-1',
            ]))
            ->assertForbidden();
    }

    public function test_admin_can_load_events_for_a_session(): void
    {
        $exam = $this->cameraExam();
        $student = $this->makeStudent($this->classroom);
        $session = ExamSession::factory()->create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
        ]);
        ProctorEvent::create([
            'exam_session_id' => $session->id,
            'source' => 'laptop',
            'type' => 'no_face',
        ]);

        $this->actingAs($this->makeAdmin())
            ->getJson(route('admin.proctor.events', $session))
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.type', 'no_face');
    }
}
