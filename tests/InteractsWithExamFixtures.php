<?php

namespace Tests;

use App\Models\Answer;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Module;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Builders for the fixtures the controllers expect: a course with a module, an
 * exam scheduled against a classroom, and MCQ questions with four options.
 *
 * These helpers keep each test focused on the behaviour under test instead of
 * re-declaring the same six models every time.
 */
trait InteractsWithExamFixtures
{
    /**
     * Course -> module chain, used by question-bank and exam routes.
     */
    protected function makeCourseWithModule(int $questionCount = 0): array
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create(['course_id' => $course->id]);

        for ($i = 0; $i < $questionCount; $i++) {
            $this->makeQuestionWithOptions($module, correctIndex: 0);
        }

        return [$course, $module];
    }

    /**
     * A question with exactly four options; the option at $correctIndex is correct.
     */
    protected function makeQuestionWithOptions(Module $module, int $correctIndex = 0, ?string $content = null): Question
    {
        $question = Question::factory()->create([
            'module_id' => $module->id,
            'content' => $content ?? ('Soal nomor '.($module->questions()->count() + 1).'?'),
        ]);

        foreach (range(0, 3) as $i) {
            Option::factory()->create([
                'question_id' => $question->id,
                'content' => 'Pilihan '.chr(65 + $i),
                'is_correct' => $i === $correctIndex,
            ]);
        }

        return $question->fresh();
    }

    /**
     * An exam wired to a module, a course and a classroom with a schedule window.
     *
     * @param  array<string, mixed>  $pivot  overrides for the exam_classroom pivot
     */
    protected function makeExamWithSchedule(
        Module $module,
        Classroom $classroom,
        array $pivot = [],
    ): Exam {
        $exam = Exam::factory()->create([
            'course_id' => $module->course_id,
            'module_id' => $module->id,
        ]);

        $exam->classrooms()->attach($classroom->id, array_merge([
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'duration_minutes' => 60,
            'pin' => null,
            'is_active' => true,
        ], $pivot));

        return $exam->fresh();
    }

    /**
     * A student enrolled in $classroom.
     */
    protected function makeStudent(?Classroom $classroom = null): User
    {
        return User::factory()->student($classroom)->create();
    }

    /**
     * An admin account.
     */
    protected function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Record an answer row pointing at the correct option of $question.
     */
    protected function answerCorrectly(ExamSession $session, Question $question): Answer
    {
        $option = $question->options()->where('is_correct', true)->first();

        return Answer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'option_id' => $option->id,
        ]);
    }

    /**
     * Record an answer row pointing at a wrong option of $question.
     */
    protected function answerIncorrectly(ExamSession $session, Question $question): Answer
    {
        $option = $question->options()->where('is_correct', false)->first();

        return Answer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'option_id' => $option->id,
        ]);
    }

    /**
     * Freeze time so schedule-boundary behaviour is deterministic.
     */
    protected function freezeClock(string $moment = '2026-06-01 10:00:00'): void
    {
        $this->travelTo(Carbon::parse($moment));
    }
}
