<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ProctorEvent;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class ExamControllerTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private function admin()
    {
        return $this->actingAs($this->makeAdmin());
    }

    /**
     * Valid payload for the nested classroom schedule the store/update routes require.
     */
    private function payload(int $moduleId, int $classroomId, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Ujian Praktikum Modul 1',
            'description' => 'Deskripsi ujian',
            'module_id' => $moduleId,
            'passing_grade' => 70,
            'max_attempts' => 1,
            'max_tab_switches' => 3,
            'is_active' => 1,
            'require_fullscreen' => 0,
            'classrooms' => [[
                'classroom_id' => $classroomId,
                'start_time' => now()->addHour()->toDateTimeString(),
                'end_time' => now()->addHours(3)->toDateTimeString(),
                'duration_minutes' => 60,
                'pin' => '1234',
                'is_active' => 1,
            ]],
        ], $overrides);
    }

    public function test_index_lists_exams(): void
    {
        [, $module] = $this->makeCourseWithModule(3);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $this->admin()->get(route('admin.exams.index'))
            ->assertOk()
            ->assertViewIs('admin.exams.index')
            ->assertSee($exam->title);
    }

    public function test_index_filters_by_search_term(): void
    {
        [$course, $module] = $this->makeCourseWithModule();
        $course->update(['name' => 'Kursus Uji Filter']);
        $classroom = Classroom::factory()->create();
        $match = $this->makeExamWithSchedule($module, $classroom);
        $match->update(['title' => 'Jaringan Komputer']);

        $other = Exam::factory()->create(['title' => 'Basis Data', 'course_id' => $course->id, 'module_id' => $module->id]);

        $response = $this->admin()->get(route('admin.exams.index', ['search' => 'Jaringan']));

        $response->assertOk()->assertSee('Jaringan Komputer')->assertDontSee($other->title);
    }

    public function test_index_filters_by_course(): void
    {
        [$courseA, $moduleA] = $this->makeCourseWithModule();
        [$courseB, $moduleB] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();

        $examA = $this->makeExamWithSchedule($moduleA, $classroom);
        $examA->update(['title' => 'Kuliah Alfa']);
        $examB = $this->makeExamWithSchedule($moduleB, $classroom);
        $examB->update(['title' => 'Kuliah Beta']);

        $this->admin()->get(route('admin.exams.index', ['course_id' => $courseA->id]))
            ->assertOk()
            ->assertSee('Kuliah Alfa')
            ->assertDontSee('Kuliah Beta');
    }

    public function test_index_filters_by_status_inactive(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $exam->update(['title' => 'Ujian Nonaktif', 'is_active' => false]);

        $this->admin()->get(route('admin.exams.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Ujian Nonaktif');
    }

    public function test_index_can_group_exams_by_course(): void
    {
        [$course, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom);
        $exam->update(['title' => 'Ujian Grup']);

        $this->admin()->get(route('admin.exams.index', ['group' => 'course']))
            ->assertOk()
            ->assertSee('Ujian Grup');
    }

    public function test_create_renders_the_form_with_modules_and_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $classroom = Classroom::factory()->create(['name' => 'IF-A1']);

        $this->admin()->get(route('admin.exams.create'))
            ->assertOk()
            ->assertViewIs('admin.exams.create')
            ->assertSee('IF-A1');
    }

    public function test_store_creates_exam_and_pivots_schedule_pin_and_active_flag(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();

        $this->admin()->post(route('admin.exams.store'), $this->payload($module->id, $classroom->id))
            ->assertRedirect(route('admin.exams.index'))
            ->assertSessionHas('success');

        $exam = Exam::first();
        $this->assertSame('Ujian Praktikum Modul 1', $exam->title);
        $this->assertSame($module->course_id, $exam->course_id, 'course_id should be derived from the module');
        $this->assertTrue($exam->is_active);

        $this->assertDatabaseHas('exam_classroom', [
            'exam_id' => $exam->id,
            'classroom_id' => $classroom->id,
            'duration_minutes' => 60,
            'pin' => '1234',
            'is_active' => 1,
        ]);
    }

    public function test_store_normalizes_empty_pin_to_null(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();

        $payload = $this->payload($module->id, $classroom->id);
        $payload['classrooms'][0]['pin'] = '';

        $this->admin()->post(route('admin.exams.store'), $payload)->assertRedirect(route('admin.exams.index'));

        $this->assertDatabaseHas('exam_classroom', [
            'exam_id' => Exam::first()->id,
            'pin' => null,
        ]);
    }

    public function test_store_supports_multiple_classrooms_with_independent_schedules(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classA = Classroom::factory()->create();
        $classB = Classroom::factory()->create();

        $payload = $this->payload($module->id, $classA->id);
        $payload['classrooms'][] = [
            'classroom_id' => $classB->id,
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDays(2)->toDateTimeString(),
            'duration_minutes' => 90,
            'pin' => '9999',
            'is_active' => 1,
        ];

        $this->admin()->post(route('admin.exams.store'), $payload)->assertRedirect(route('admin.exams.index'));

        $exam = Exam::first();
        $this->assertSame(2, $exam->classrooms()->count());
        $this->assertDatabaseHas('exam_classroom', [
            'exam_id' => $exam->id,
            'classroom_id' => $classB->id,
            'duration_minutes' => 90,
            'pin' => '9999',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->admin()->post(route('admin.exams.store'), [])
            ->assertSessionHasErrors(['title', 'module_id', 'classrooms', 'passing_grade', 'max_attempts']);

        $this->assertDatabaseCount('exams', 0);
    }

    public function test_store_rejects_end_time_before_start_time(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();

        $payload = $this->payload($module->id, $classroom->id);
        $payload['classrooms'][0]['end_time'] = now()->subHour()->toDateTimeString();

        $this->admin()->post(route('admin.exams.store'), $payload)
            ->assertSessionHasErrors('classrooms.0.end_time');

        $this->assertDatabaseCount('exams', 0);
    }

    public function test_store_rejects_out_of_range_passing_grade_and_attempts(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();

        $this->admin()->post(route('admin.exams.store'), $this->payload($module->id, $classroom->id, [
            'passing_grade' => 150,
            'max_attempts' => 99,
        ]))->assertSessionHasErrors(['passing_grade', 'max_attempts']);
    }

    public function test_store_rejects_nonexistent_module(): void
    {
        $classroom = Classroom::factory()->create();

        $this->admin()->post(route('admin.exams.store'), $this->payload(9999, $classroom->id))
            ->assertSessionHasErrors('module_id');
    }

    public function test_edit_renders_the_form_for_an_exam(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $this->admin()->get(route('admin.exams.edit', $exam))
            ->assertOk()
            ->assertViewIs('admin.exams.edit')
            ->assertSee($exam->title);
    }

    public function test_update_changes_exam_fields_and_resyncs_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['pin' => '1111']);

        $this->admin()->put(route('admin.exams.update', $exam), $this->payload($module->id, $otherClassroom->id, [
            'title' => 'Judul Diperbarui',
            'passing_grade' => 80,
            'max_attempts' => 3,
        ]))
            ->assertRedirect(route('admin.exams.index'))
            ->assertSessionHas('success');

        $exam->refresh();
        $this->assertSame('Judul Diperbarui', $exam->title);
        $this->assertSame(80, $exam->passing_grade);
        $this->assertSame(3, $exam->max_attempts);

        // sync() replaces the previous classroom association.
        $this->assertSame([$otherClassroom->id], $exam->classrooms()->pluck('classrooms.id')->all());
    }

    public function test_store_rejects_duplicate_classroom_and_oversized_duration(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();

        $dup = $this->payload($module->id, $classroom->id, [
            'classrooms' => [
                [
                    'classroom_id' => $classroom->id,
                    'start_time' => now()->addHour()->toDateTimeString(),
                    'end_time' => now()->addHours(3)->toDateTimeString(),
                    'duration_minutes' => 60,
                    'pin' => null,
                    'is_active' => 1,
                ],
                [
                    'classroom_id' => $classroom->id,
                    'start_time' => now()->addHour()->toDateTimeString(),
                    'end_time' => now()->addHours(3)->toDateTimeString(),
                    'duration_minutes' => 60,
                    'pin' => null,
                    'is_active' => 1,
                ],
            ],
        ]);
        $this->admin()->post(route('admin.exams.store'), $dup)->assertSessionHasErrors('classrooms.1.classroom_id');

        $long = $this->payload($module->id, $classroom->id, [
            'classrooms' => [[
                'classroom_id' => $classroom->id,
                'start_time' => now()->addHour()->toDateTimeString(),
                'end_time' => now()->addHours(2)->toDateTimeString(),
                'duration_minutes' => 120,
                'pin' => null,
                'is_active' => 1,
            ]],
        ]);
        $this->admin()->post(route('admin.exams.store'), $long)->assertSessionHasErrors('classrooms.0.duration_minutes');
    }

    public function test_update_is_blocked_when_detaching_classroom_with_live_session(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);
        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->admin()->put(route('admin.exams.update', $exam), $this->payload($module->id, $otherClassroom->id))
            ->assertSessionHasErrors('classrooms');

        $this->assertTrue($exam->classrooms()->where('classrooms.id', $classroom->id)->exists());
    }

    public function test_destroy_deletes_the_exam(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $this->admin()->delete(route('admin.exams.destroy', $exam))
            ->assertRedirect(route('admin.exams.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
        $this->assertDatabaseMissing('exam_classroom', ['exam_id' => $exam->id]);
    }

    public function test_destroy_is_blocked_when_sessions_exist(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $this->admin()->delete(route('admin.exams.destroy', $exam))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    public function test_show_displays_questions_with_answer_keys(): void
    {
        [$course, $module] = $this->makeCourseWithModule(2);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $this->admin()->get(route('admin.exams.show', $exam))
            ->assertOk()
            ->assertViewIs('admin.exams.show');
    }

    public function test_results_groups_sessions_by_student(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        ExamSession::factory()->finished(90)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $this->admin()->get(route('admin.exams.results', $exam))
            ->assertOk()
            ->assertViewIs('admin.exams.results')
            ->assertViewHas('students');
    }

    public function test_results_csv_streams_a_utf8_bom_and_rows(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        ExamSession::factory()->finished(85)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $response = $this->admin()->get(route('admin.exams.results.csv', $exam));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'CSV should start with a UTF-8 BOM for Excel');
        $this->assertStringContainsString('Nama Mahasiswa', $csv);
        $this->assertStringContainsString($student->username, $csv);
        $this->assertStringContainsString('LULUS', $csv);
    }

    public function test_results_csv_marks_failing_scores_as_gagal(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        ExamSession::factory()->finished(40)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $response = $this->admin()->get(route('admin.exams.results.csv', $exam));
        $response->assertOk();

        $this->assertStringContainsString('GAGAL', $response->streamedContent());
    }

    public function test_results_csv_sanitizes_formula_injection_in_names(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);
        $student->update(['name' => '=cmd|calc']);

        ExamSession::factory()->finished(85)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $response = $this->admin()->get(route('admin.exams.results.csv', $exam));
        $response->assertOk();

        $csv = $response->streamedContent();
        // The raw formula is neutralised with a leading apostrophe.
        $this->assertStringContainsString("'=cmd|calc", $csv);
    }

    public function test_monitor_lists_participants_by_status(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $notStarted = $this->makeStudent($classroom);
        $inProgress = $this->makeStudent($classroom);
        $finished = $this->makeStudent($classroom);

        ExamSession::factory()->create(['user_id' => $inProgress->id, 'exam_id' => $exam->id]);
        ExamSession::factory()->finished(95)->create(['user_id' => $finished->id, 'exam_id' => $exam->id]);

        $this->admin()->get(route('admin.exams.monitor', $exam))
            ->assertOk()
            ->assertViewIs('admin.exams.monitor')
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 3
                && $stats['not_started'] === 1
                && $stats['in_progress'] === 1
                && $stats['finished'] === 1);
    }

    public function test_monitor_returns_json_for_ajax_polling(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);
        ExamSession::factory()->finished(95)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $response = $this->admin()->get(route('admin.exams.monitor', $exam), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['participants', 'stats' => ['total', 'not_started', 'in_progress', 'finished'], 'time']);

        $participant = $response->json('participants.'.$student->username);
        $this->assertSame('finished', $participant['status']);
        $this->assertSame('95.00', $participant['score']);
    }

    public function test_monitor_returns_camera_status_and_event_count(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);
        $session = ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $session->update([
            'laptop_seen_at' => now(),
            'phone_seen_at' => now()->subMinute(),
        ]);
        ProctorEvent::create([
            'exam_session_id' => $session->id,
            'source' => 'laptop',
            'type' => 'no_face',
        ]);

        $response = $this->admin()->get(route('admin.exams.monitor', $exam), [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('participants.'.$student->username.'.session_id', $session->id)
            ->assertJsonPath('participants.'.$student->username.'.laptop_online', true)
            ->assertJsonPath('participants.'.$student->username.'.phone_online', false)
            ->assertJsonPath('participants.'.$student->username.'.proctor_event_count', 1);
    }

    public function test_monitor_excludes_students_from_inactive_classrooms(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        $activeClass = Classroom::factory()->create();
        $inactiveClass = Classroom::factory()->create();

        $exam = $this->makeExamWithSchedule($module, $activeClass, ['start_time' => now()]);
        $exam->classrooms()->attach($inactiveClass->id, [
            'start_time' => now()->subHour(), 'end_time' => now()->addHours(2),
            'duration_minutes' => 60, 'is_active' => false,
        ]);

        $this->makeStudent($inactiveClass);

        $response = $this->admin()->get(route('admin.exams.monitor', $exam), [
            'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json',
        ]);

        $response->assertOk()->assertJsonPath('stats.total', 0);
    }

    public function test_export_pdf_returns_a_pdf_attachment_with_sanitized_filename(): void
    {
        [, $module] = $this->makeCourseWithModule(2);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $exam->update(['title' => 'Ujian/<script>:2026']);

        $student = $this->makeStudent($classroom);
        ExamSession::factory()->finished(90)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $response = $this->admin()->get(route('admin.exams.pdf', $exam));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Laporan_Nilai_Ujian__script__2026.pdf', $response->headers->get('content-disposition'));
    }

    public function test_student_report_shows_answers_for_each_attempt(): void
    {
        [$course, $module] = $this->makeCourseWithModule(2);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(50)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $question = $module->questions()->first();
        $this->answerCorrectly($session, $question);

        $this->admin()->get(route('admin.exams.student_report', [$exam, $student]))
            ->assertOk()
            ->assertViewIs('admin.exams.student-report');
    }

    public function test_student_report_redirects_when_student_has_no_sessions(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $this->admin()->get(route('admin.exams.student_report', [$exam, $student]))
            ->assertRedirect(route('admin.exams.results', $exam))
            ->assertSessionHas('error');
    }

    // ------------------------------------------------------------- grading

    private function codingAnswer(ExamSession $session, Question $question, ?int $score = null): Answer
    {
        return Answer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'answer_text' => 'print("jawaban")',
            'grade_status' => $score === null ? Answer::GRADE_PENDING : Answer::GRADE_GRADED,
            'score' => $score,
        ]);
    }

    public function test_grade_coding_saves_score_and_recomputes_session(): void
    {
        [, $module] = $this->makeCourseWithModule(1);
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);
        $mcq = $module->questions()->where('type', Question::TYPE_MULTIPLE_CHOICE)->first();
        $this->answerCorrectly($session, $mcq);
        $answer = $this->codingAnswer($session, $coding);

        $this->admin()->post(route('admin.exams.grade_coding', [$exam, $session]), [
            'scores' => [$answer->id => 100],
        ])->assertRedirect();

        $answer->refresh();
        $this->assertSame(Answer::GRADE_GRADED, $answer->grade_status);
        $this->assertEquals(100.0, (float) $answer->score);

        $session->refresh();
        $this->assertEquals(100.0, (float) $session->score);
        $this->assertNotNull($session->graded_at);
    }

    public function test_grade_coding_keeps_session_pending_until_all_answers_scored(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $first = $this->makeCodingQuestion($module);
        $second = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'graded_at' => null,
        ]);
        $answerA = $this->codingAnswer($session, $first);
        $this->codingAnswer($session, $second);

        $this->admin()->post(route('admin.exams.grade_coding', [$exam, $session]), [
            'scores' => [$answerA->id => 50],
        ])->assertRedirect();

        $this->assertNull($session->fresh()->graded_at);
    }

    public function test_grade_coding_ignores_answers_belonging_to_another_session(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $owner = $this->makeStudent($classroom);
        $other = $this->makeStudent($classroom);

        $ownerSession = ExamSession::factory()->finished(0)->create(['user_id' => $owner->id, 'exam_id' => $exam->id]);
        $otherSession = ExamSession::factory()->finished(0)->create(['user_id' => $other->id, 'exam_id' => $exam->id]);
        $foreignAnswer = $this->codingAnswer($ownerSession, $coding);

        $this->admin()->post(route('admin.exams.grade_coding', [$exam, $otherSession]), [
            'scores' => [$foreignAnswer->id => 100],
        ])->assertRedirect();

        $this->assertSame(Answer::GRADE_PENDING, $foreignAnswer->fresh()->grade_status);
        $this->assertNull($foreignAnswer->fresh()->score);
    }

    public function test_grade_coding_validates_score_range(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $answer = $this->codingAnswer($session, $coding);

        $this->admin()->post(route('admin.exams.grade_coding', [$exam, $session]), [
            'scores' => [$answer->id => 150],
        ])->assertSessionHasErrors('scores.'.$answer->id);
    }

    public function test_grade_coding_marks_score_as_manual(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $answer = $this->codingAnswer($session, $coding);

        $this->admin()->post(route('admin.exams.grade_coding', [$exam, $session]), [
            'scores' => [$answer->id => 80],
        ])->assertRedirect();

        $this->assertSame(Answer::GRADED_BY_MANUAL, $answer->fresh()->graded_by);
    }

    private function groqOk(string $content): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $content]]]]),
        ]);
    }

    public function test_ai_grade_coding_grades_pending_answers(): void
    {
        $this->groqOk('{"score": 85, "feedback": "Bagus, kurang edge case."}');

        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $answer = $this->codingAnswer($session, $coding);

        $this->admin()->post(route('admin.exams.ai_grade_coding', [$exam, $session]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $answer->refresh();
        $this->assertSame(Answer::GRADE_GRADED, $answer->grade_status);
        $this->assertSame(Answer::GRADED_BY_AI, $answer->graded_by);
        $this->assertEquals(85.0, (float) $answer->score);
        $this->assertSame('Bagus, kurang edge case.', $answer->ai_grade_detail['feedback']);
        $this->assertNotNull($session->fresh()->graded_at);
    }

    public function test_ai_grade_coding_skips_manual_scores_unless_forced(): void
    {
        $this->groqOk('{"score": 90, "feedback": "OK."}');

        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $answer = $this->codingAnswer($session, $coding, 60);
        $answer->update(['graded_by' => Answer::GRADED_BY_MANUAL, 'ai_grade_detail' => null]);

        $this->admin()->post(route('admin.exams.ai_grade_coding', [$exam, $session]))->assertRedirect();

        $this->assertEquals(60.0, (float) $answer->fresh()->score);
        $this->assertSame(Answer::GRADED_BY_MANUAL, $answer->fresh()->graded_by);

        $this->admin()->post(route('admin.exams.ai_grade_coding', [$exam, $session]), ['force' => 1])->assertRedirect();

        $answer->refresh();
        $this->assertEquals(90.0, (float) $answer->score);
        $this->assertSame(Answer::GRADED_BY_AI, $answer->graded_by);
    }

    public function test_ai_grade_coding_leaves_pending_when_ai_fails(): void
    {
        Http::fake(['api.groq.com/*' => Http::response('limit', 429)]);

        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $answer = $this->codingAnswer($session, $coding);

        $this->admin()->post(route('admin.exams.ai_grade_coding', [$exam, $session]))->assertRedirect();

        $this->assertSame(Answer::GRADE_PENDING, $answer->fresh()->grade_status);
        $this->assertNull($answer->fresh()->graded_by);
    }

    public function test_student_report_shows_ai_feedback_to_admin(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $answer = $this->codingAnswer($session, $coding, 85);
        $answer->update([
            'graded_by' => Answer::GRADED_BY_AI,
            'ai_grade_detail' => ['score' => 85, 'feedback' => 'Rahasia dapur AI.', 'model' => 'x'],
        ]);

        $this->admin()->get(route('admin.exams.student_report', [$exam, $student]))
            ->assertOk()
            ->assertSee('Rahasia dapur AI.');
    }

    public function test_grade_coding_is_forbidden_for_students(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create(['user_id' => $student->id, 'exam_id' => $exam->id]);
        $answer = $this->codingAnswer($session, $coding);

        $this->actingAs($student)->post(route('admin.exams.grade_coding', [$exam, $session]), [
            'scores' => [$answer->id => 100],
        ])->assertForbidden();
    }

    public function test_results_flags_sessions_that_still_need_manual_grading(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'graded_at' => null,
        ]);
        $this->codingAnswer($session, $coding);

        $this->admin()->get(route('admin.exams.results', $exam))
            ->assertOk()
            ->assertSee('Perlu dinilai');
    }

    public function test_results_ignores_blank_coding_answers(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(0)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'graded_at' => now(),
        ]);
        Answer::create([
            'exam_session_id' => $session->id,
            'question_id' => $coding->id,
            'answer_text' => '   ',
            'grade_status' => Answer::GRADE_PENDING,
            'score' => null,
        ]);

        $this->admin()->get(route('admin.exams.results', $exam))
            ->assertOk()
            ->assertDontSee('Perlu dinilai');
    }

    public function test_student_report_is_forbidden_for_non_students_and_outsiders(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $outsider = $this->makeStudent($otherClassroom);

        $this->admin()->get(route('admin.exams.student_report', [$exam, $outsider]))->assertForbidden();

        $admin = $this->makeAdmin();
        $this->admin()->get(route('admin.exams.student_report', [$exam, $admin]))->assertForbidden();
    }

    public function test_run_code_returns_output_for_admin(): void
    {
        Http::fake([
            'wandbox.org/*' => Http::response(['status' => '0', 'program_message' => "6\n"]),
        ]);

        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module, 'sql');
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $this->admin()->postJson(route('admin.exams.run_code', $exam), [
            'question_id' => $coding->id,
            'code' => 'SELECT 2*3;',
        ])->assertOk()->assertJson(['ok' => true, 'output' => "6\n"]);

        Http::assertSent(fn ($request) => $request['compiler'] === 'sqlite-3.46.1');
    }

    public function test_run_code_forwards_stdin_for_admin(): void
    {
        Http::fake([
            'wandbox.org/*' => Http::response(['status' => '0', 'program_message' => "hello\n"]),
        ]);

        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module, 'python');
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $this->admin()->postJson(route('admin.exams.run_code', $exam), [
            'question_id' => $coding->id,
            'code' => 'print(input())',
            'stdin' => 'hello',
        ])->assertOk()->assertJson(['ok' => true]);

        Http::assertSent(fn ($request) => $request['stdin'] === 'hello');
    }

    public function test_run_code_rejects_a_non_coding_question_for_admin(): void
    {
        Http::fake();

        [, $module] = $this->makeCourseWithModule(1);
        $mcq = $module->questions()->first();
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $this->admin()->postJson(route('admin.exams.run_code', $exam), [
            'question_id' => $mcq->id,
            'code' => 'print(1)',
        ])->assertStatus(400);

        Http::assertNothingSent();
    }

    public function test_run_code_is_forbidden_for_students(): void
    {
        Http::fake();

        [, $module] = $this->makeCourseWithModule();
        $coding = $this->makeCodingQuestion($module);
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $this->actingAs($student)->postJson(route('admin.exams.run_code', $exam), [
            'question_id' => $coding->id,
            'code' => 'print(1)',
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_guests_cannot_access_exam_management(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $this->get(route('admin.exams.index'))->assertRedirect(route('login'));
        $this->get(route('admin.exams.create'))->assertRedirect(route('login'));
        $this->post(route('admin.exams.store'), $this->payload($module->id, $classroom->id))->assertRedirect(route('login'));
        $this->get(route('admin.exams.show', $exam))->assertRedirect(route('login'));
        $this->get(route('admin.exams.results', $exam))->assertRedirect(route('login'));
        $this->get(route('admin.exams.monitor', $exam))->assertRedirect(route('login'));
        $this->get(route('admin.exams.pdf', $exam))->assertRedirect(route('login'));
        $this->delete(route('admin.exams.destroy', $exam))->assertRedirect(route('login'));

        $this->assertDatabaseCount('exams', 1);
    }

    public function test_students_cannot_access_exam_management(): void
    {
        [, $module] = $this->makeCourseWithModule();
        $classroom = Classroom::factory()->create();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $this->actingAs($student)->get(route('admin.exams.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.exams.results', $exam))->assertForbidden();
        $this->actingAs($student)->get(route('admin.exams.monitor', $exam))->assertForbidden();
        $this->actingAs($student)->get(route('admin.exams.pdf', $exam))->assertForbidden();
        $this->actingAs($student)->delete(route('admin.exams.destroy', $exam))->assertForbidden();

        $this->assertDatabaseCount('exams', 1);
    }
}
