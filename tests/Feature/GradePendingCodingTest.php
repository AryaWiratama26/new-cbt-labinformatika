<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Classroom;
use App\Models\ExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class GradePendingCodingTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classroom = Classroom::factory()->create();
    }

    private function student()
    {
        return $this->makeStudent($this->classroom);
    }

    private function groqOk(string $content = '{"score": 85, "feedback": "Bagus."}'): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $content]]]]),
        ]);
    }

    public function test_grades_pending_coding_answers_in_finished_sessions(): void
    {
        $this->groqOk();

        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->finished(0)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'graded_at' => null,
        ]);
        $answer = Answer::create([
            'exam_session_id' => $session->id,
            'question_id' => $coding->id,
            'answer_text' => 'print(1)',
            'grade_status' => Answer::GRADE_PENDING,
            'score' => null,
        ]);

        $this->artisan('grade:coding-pending')->assertOk();

        $answer->refresh();
        $this->assertSame(Answer::GRADE_GRADED, $answer->grade_status);
        $this->assertSame(Answer::GRADED_BY_AI, $answer->graded_by);
        $this->assertEquals(85.0, (float) $answer->score);
        $this->assertSame('Bagus.', $answer->ai_grade_detail['feedback']);
        $this->assertNotNull($session->fresh()->graded_at);
    }

    public function test_skips_unfinished_sessions_and_manual_scores(): void
    {
        $this->groqOk();

        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $openSession = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $openAnswer = Answer::create([
            'exam_session_id' => $openSession->id,
            'question_id' => $coding->id,
            'answer_text' => 'print(1)',
            'grade_status' => Answer::GRADE_PENDING,
            'score' => null,
        ]);

        $finishedSession = ExamSession::factory()->finished(0)->create([
            'user_id' => $this->makeStudent($this->classroom)->id, 'exam_id' => $exam->id, 'graded_at' => null,
        ]);
        $manualAnswer = Answer::create([
            'exam_session_id' => $finishedSession->id,
            'question_id' => $coding->id,
            'answer_text' => 'print(1)',
            'grade_status' => Answer::GRADE_GRADED,
            'score' => 70,
            'graded_by' => Answer::GRADED_BY_MANUAL,
        ]);

        $this->artisan('grade:coding-pending')->assertOk();

        $this->assertSame(Answer::GRADE_PENDING, $openAnswer->fresh()->grade_status);
        $this->assertEquals(70.0, (float) $manualAnswer->fresh()->score);
        Http::assertNothingSent();
    }

    public function test_stops_when_rate_limited(): void
    {
        Http::fake(['api.groq.com/*' => Http::response('limit', 429)]);

        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $exam = $this->makeExamWithSchedule($module, $this->classroom, ['start_time' => now()]);
        $student = $this->student();

        $session = ExamSession::factory()->finished(0)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'graded_at' => null,
        ]);
        $answer = Answer::create([
            'exam_session_id' => $session->id,
            'question_id' => $coding->id,
            'answer_text' => 'print(1)',
            'grade_status' => Answer::GRADE_PENDING,
            'score' => null,
        ]);

        $this->artisan('grade:coding-pending')->assertOk();

        $this->assertSame(Answer::GRADE_PENDING, $answer->fresh()->grade_status);
    }
}
