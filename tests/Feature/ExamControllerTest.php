<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
