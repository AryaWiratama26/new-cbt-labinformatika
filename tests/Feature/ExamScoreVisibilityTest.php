<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class ExamScoreVisibilityTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classroom = Classroom::factory()->create();
    }

    private function finishedSession(): array
    {
        [, $module] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $this->classroom);
        $student = $this->makeStudent($this->classroom);

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();

        $session = ExamSession::where('user_id', $student->id)->where('exam_id', $exam->id)->firstOrFail();
        $question = $exam->getQuestions()->first();
        $correct = $question->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $question->id,
            'option_id' => $correct->id,
        ])->assertOk();

        $this->actingAs($student)->post(route('student.exams.submit', $exam))
            ->assertRedirect(route('student.dashboard'));

        return [$exam->fresh(), $student, $session->fresh()];
    }

    public function test_scores_visible_by_default(): void
    {
        [$exam, $student] = $this->finishedSession();

        $this->assertTrue((bool) $exam->show_score_to_students);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Nilai:', false);

        $this->actingAs($student)->get(route('student.history'))
            ->assertOk()
            ->assertSee('100.00', false);

        $session = ExamSession::where('user_id', $student->id)->where('exam_id', $exam->id)->firstOrFail();
        $this->actingAs($student)->get(route('student.history.review', $session))
            ->assertOk()
            ->assertSee('Nilai Akhir', false);

        $this->actingAs($student)->getJson(route('student.history.grade_status', $session))
            ->assertOk()
            ->assertJson(['score' => 100.0]);
    }

    public function test_scores_hidden_when_toggle_is_off(): void
    {
        [$exam, $student] = $this->finishedSession();
        $exam->update(['show_score_to_students' => false]);

        // Dashboard: no numeric score, card itself still renders.
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Nilai:', false);

        // History table: dash instead of number.
        $response = $this->actingAs($student)->get(route('student.history'))->assertOk();
        $response->assertDontSee('100.00', false);

        // Review: no Nilai Akhir number, no per-answer Nilai badge.
        $session = ExamSession::where('user_id', $student->id)->where('exam_id', $exam->id)->firstOrFail();
        $this->actingAs($student)->get(route('student.history.review', $session))
            ->assertOk()
            ->assertDontSee('Nilai Akhir', false)
            ->assertDontSee('Nilai: 100/100', false);

        // Polling endpoint leaks no score.
        $this->actingAs($student)->getJson(route('student.history.grade_status', $session))
            ->assertOk()
            ->assertJson(['score' => null]);

        // Retake still possible: status badges (not scores) drive remedial.
        $this->assertNotNull($session->score);
    }

    public function test_admin_can_toggle_score_visibility_on_create_and_edit(): void
    {
        $admin = $this->makeAdmin();
        [, $module] = $this->makeCourseWithModule(1);

        $payload = function (int $show) use ($module) {
            return [
                'title' => 'Ujian Toggle',
                'module_id' => $module->id,
                'classrooms' => [[
                    'classroom_id' => $this->classroom->id,
                    'start_time' => now()->subHour()->format('Y-m-d H:i:s'),
                    'end_time' => now()->addHours(2)->format('Y-m-d H:i:s'),
                    'duration_minutes' => 60,
                    'is_active' => true,
                    'pin' => null,
                ]],
                'is_active' => true,
                'show_score_to_students' => $show,
                'passing_grade' => 70,
                'max_attempts' => 1,
            ];
        };

        $this->actingAs($admin)->post(route('admin.exams.store'), $payload(0))->assertRedirect();
        $exam = Exam::where('title', 'Ujian Toggle')->firstOrFail();
        $this->assertFalse((bool) $exam->show_score_to_students);

        $payloadEdit = $payload(1);
        $payloadEdit['title'] = 'Ujian Toggle Edit';
        $this->actingAs($admin)->put(route('admin.exams.update', $exam), $payloadEdit)->assertRedirect();
        $this->assertTrue((bool) $exam->fresh()->show_score_to_students);
    }
}
