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

class ExamEssayTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classroom = Classroom::factory()->create();
    }

    private function examWithEssay(string $content = 'Jelaskan perbedaan RAM dan ROM.'): array
    {
        [, $module] = $this->makeCourseWithModule(1);
        $essay = $module->questions()->create([
            'content' => $content,
            'category' => 'sedang',
            'type' => Question::TYPE_ESSAY,
        ]);
        $exam = $this->makeExamWithSchedule($module, $this->classroom);

        return [$exam->fresh(), $module, $essay];
    }

    public function test_admin_can_create_essay_question_without_options(): void
    {
        $admin = $this->makeAdmin();
        [, $module] = $this->makeCourseWithModule(0);

        $this->actingAs($admin)->post(
            route('admin.courses.modules.questions.store', [$module->course_id, $module]),
            ['content' => 'Jelaskan...', 'type' => 'essay']
        )->assertRedirect();

        $question = $module->questions()->first();
        $this->assertNotNull($question);
        $this->assertSame(Question::TYPE_ESSAY, $question->type);
        $this->assertTrue($question->isEssay());
        $this->assertTrue($question->needsManualGrading());
        $this->assertFalse($question->isCoding());
        $this->assertCount(0, $question->options);
    }

    public function test_essay_type_is_validated(): void
    {
        $admin = $this->makeAdmin();
        [, $module] = $this->makeCourseWithModule(0);

        $this->actingAs($admin)->post(
            route('admin.courses.modules.questions.store', [$module->course_id, $module]),
            ['content' => 'Jelaskan...', 'type' => 'puisi']
        )->assertSessionHasErrors('type');

        $this->assertSame(0, $module->questions()->count());
    }

    public function test_student_flow_essay_stays_pending_until_graded(): void
    {
        [$exam] = $this->examWithEssay();
        $student = $this->makeStudent($this->classroom);

        // Start.
        $this->actingAs($student)->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.attempt', $exam));

        $session = ExamSession::where('user_id', $student->id)->where('exam_id', $exam->id)->first();
        $this->assertNotNull($session);

        $essay = $exam->getQuestions()->first(fn ($q) => $q->isEssay());

        // Autosave jawaban essay.
        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $essay->id,
            'option_id' => null,
            'answer_text' => 'RAM itu memori sementara...',
        ])->assertOk()->assertJson(['success' => true]);

        $answer = Answer::where('exam_session_id', $session->id)->where('question_id', $essay->id)->first();
        $this->assertNotNull($answer);
        $this->assertSame(Answer::GRADE_PENDING, $answer->grade_status);
        $this->assertNull($answer->option_id);

        // Submit: 1 MC benar + 1 essay pending => skor 50, graded_at null.
        $mc = $exam->getQuestions()->first(fn ($q) => ! $q->needsManualGrading());
        $correct = $mc->options()->where('is_correct', true)->first();

        $this->actingAs($student)->post(route('student.exams.submit', $exam), [
            'answers' => [$mc->id => $correct->id],
        ])->assertRedirect(route('student.dashboard'));

        $session->refresh();
        $this->assertNotNull($session->finished_at);
        $this->assertEquals(50.0, (float) $session->score);
        $this->assertNull($session->graded_at);

        // Koreksi manual oleh admin.
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(
            route('admin.exams.grade_coding', [$exam, $session]),
            ['scores' => [$answer->id => 80]]
        )->assertRedirect();

        $this->assertSame(Answer::GRADE_GRADED, $answer->fresh()->grade_status);
        $this->assertSame(Answer::GRADED_BY_MANUAL, $answer->fresh()->graded_by);
        $session->refresh();
        $this->assertEquals(90.0, (float) $session->score);
        $this->assertNotNull($session->graded_at);
    }

    public function test_review_page_shows_essay_answer_instead_of_unanswered(): void
    {
        [$exam] = $this->examWithEssay();
        $student = $this->makeStudent($this->classroom);

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();

        $essay = $exam->getQuestions()->first(fn ($q) => $q->isEssay());
        $session = ExamSession::where('user_id', $student->id)->where('exam_id', $exam->id)->first();

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $essay->id,
            'option_id' => null,
            'answer_text' => 'Jokowi adalah presiden ketujuh.',
        ])->assertOk();

        $this->actingAs($student)->post(route('student.exams.submit', $exam), ['answers' => []])
            ->assertRedirect(route('student.dashboard'));

        $session->refresh();

        $this->actingAs($student)->get(route('student.history.review', $session))
            ->assertOk()
            ->assertSee('Soal Essay', false)
            ->assertSee('Jokowi adalah presiden ketujuh.', false)
            ->assertSee('Menunggu penilaian', false);

        // After manual grading, the score badge appears on the review page.
        $answer = Answer::where('exam_session_id', $session->id)->where('question_id', $essay->id)->first();
        $this->actingAs($this->makeAdmin())->post(
            route('admin.exams.grade_coding', [$exam, $session]),
            ['scores' => [$answer->id => 80]]
        )->assertRedirect();

        $this->actingAs($student)->get(route('student.history.review', $session))
            ->assertOk()
            ->assertSee('Nilai: 80/100', false);
    }

    public function test_essay_answer_longer_than_limit_is_rejected(): void
    {
        [$exam] = $this->examWithEssay();
        $student = $this->makeStudent($this->classroom);

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();

        $essay = $exam->getQuestions()->first(fn ($q) => $q->isEssay());

        $this->actingAs($student)->postJson(route('student.exams.save_answer', $exam), [
            'question_id' => $essay->id,
            'option_id' => null,
            'answer_text' => str_repeat('a', 10001),
        ])->assertStatus(422);

        $this->assertSame(0, Answer::where('question_id', $essay->id)->count());
    }

    public function test_csv_import_supports_essay_rows(): void
    {
        [$exam, $module] = $this->examWithEssay();
        $admin = $this->makeAdmin();

        $csv = "Soal,Gambar,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Kunci,Kategori,Tipe\n"
            ."\"Jelaskan fotosintesis.\",,,,,,,mudah,essay\n"
            ."\"Ibukota RI?\",,Jakarta,Surabaya,Bandung,Medan,A,mudah,\n";

        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $csv);
        $file = new \Illuminate\Http\UploadedFile($tmp, 'soal.csv', 'text/csv', null, true);

        $this->actingAs($admin)->post(
            route('admin.courses.modules.import_questions', [$module->course_id, $module]),
            ['csv_file' => $file]
        )->assertRedirect();

        $this->assertTrue(
            $module->questions()->where('type', Question::TYPE_ESSAY)->where('content', 'like', '%fotosintesis%')->exists()
        );
        $this->assertTrue(
            $module->questions()->where('type', Question::TYPE_MULTIPLE_CHOICE)->where('content', 'like', '%Ibukota RI?%')->exists()
        );
    }

    public function test_switching_type_preserves_referenced_options_but_allows_essay(): void
    {
        [$exam, $module] = $this->examWithEssay();
        $admin = $this->makeAdmin();

        $mc = $module->questions()->where('type', Question::TYPE_MULTIPLE_CHOICE)->firstOrFail();
        $correct = $mc->options()->where('is_correct', true)->firstOrFail();

        // MC -> essay dengan jawaban siswa: opsi dipertahankan, tipe berubah.
        $student = $this->makeStudent(\App\Models\Classroom::first());
        \App\Models\ExamSession::factory()->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
            'finished_at' => now(), 'score' => 50,
        ])->answers()->create(['question_id' => $mc->id, 'option_id' => $correct->id, 'grade_status' => Answer::GRADE_AUTO]);

        $this->actingAs($admin)->put(
            route('admin.courses.modules.questions.update', [$module->course_id, $module, $mc]),
            ['content' => $mc->content, 'type' => 'essay']
        )->assertRedirect();

        $this->assertSame(Question::TYPE_ESSAY, $mc->fresh()->type);
        $this->assertCount(4, $mc->fresh()->options);

        // Essay -> MC: opsi dibuat baru.
        $essay = $module->questions()->where('type', Question::TYPE_ESSAY)->firstOrFail();
        $this->actingAs($admin)->put(
            route('admin.courses.modules.questions.update', [$module->course_id, $module, $essay]),
            [
                'content' => $essay->content, 'type' => 'multiple_choice',
                'options' => ['A1', 'B1', 'C1', 'D1'], 'correct_option' => '0',
            ]
        )->assertRedirect();

        $essay->refresh();
        $this->assertSame(Question::TYPE_MULTIPLE_CHOICE, $essay->type);
        $this->assertCount(4, $essay->options);
        $this->assertSame('A1', $essay->options()->where('is_correct', true)->value('content'));
    }

    public function test_docx_import_supports_essay_rows(): void
    {
        [$exam, $module] = $this->examWithEssay();
        $admin = $this->makeAdmin();

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText('1. Jelaskan fotosintesis.');
        $section->addText('Tipe: essay');
        $section->addText('Kategori: mudah');
        $section->addText('2. Siapa presiden pertama RI?');
        $section->addText('A. Soekarno');
        $section->addText('B. Soeharto');
        $section->addText('Kunci: A');

        $tmpPath = tempnam(sys_get_temp_dir(), 'soal') . '.docx';
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);

        try {
            $file = new \Illuminate\Http\UploadedFile($tmpPath, 'soal.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

            $this->actingAs($admin)->post(
                route('admin.courses.modules.import_questions.docx', [$module->course_id, $module]),
                ['docx_file' => $file]
            )->assertRedirect();
        } finally {
            @unlink($tmpPath);
        }

        $this->assertTrue(
            $module->questions()->where('type', Question::TYPE_ESSAY)->where('content', 'like', '%fotosintesis%')->exists()
        );
        $this->assertTrue(
            $module->questions()->where('type', Question::TYPE_MULTIPLE_CHOICE)->where('content', 'like', '%presiden pertama RI%')->exists()
        );
    }
}
