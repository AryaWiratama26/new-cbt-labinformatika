<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private function admin()
    {
        return $this->actingAs($this->makeAdmin());
    }

    // ---------------------------------------------------------------- dashboard

    public function test_dashboard_renders_with_zero_data(): void
    {
        $this->admin()->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertViewHasAll([
                'studentsCount', 'coursesCount', 'classroomsCount', 'examsCount',
                'examsToday', 'activeExams', 'avgScore', 'participationRate',
                'scoreDistribution', 'recentSessions', 'topStudents',
            ]);
    }

    public function test_dashboard_counts_students_and_ignores_admins(): void
    {
        $classroom = Classroom::factory()->create();
        $this->makeStudent($classroom);
        $this->makeStudent($classroom);
        $this->makeAdmin();

        $this->admin()->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('studentsCount', 2);
    }

    public function test_dashboard_computes_score_distribution_buckets(): void
    {
        [$course, $module] = $this->makeCourseWithModule(0);
        $classroom = Classroom::factory()->create();
        $exam = Exam::factory()->create(['course_id' => $course->id, 'module_id' => $module->id]);

        foreach ([[20, 'below50'], [60, '50to69'], [80, '70to85'], [95, 'above85']] as [$score, $bucket]) {
            $student = $this->makeStudent($classroom);
            ExamSession::factory()->finished($score)->create([
                'user_id' => $student->id, 'exam_id' => $exam->id,
            ]);
        }

        $this->admin()->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('scoreDistribution', fn ($dist) => $dist['below50'] === 1
                && $dist['50to69'] === 1
                && $dist['70to85'] === 1
                && $dist['above85'] === 1);
    }

    public function test_dashboard_caches_statistics_between_requests(): void
    {
        $classroom = Classroom::factory()->create();
        $this->makeStudent($classroom);

        $this->admin()->get(route('admin.dashboard'))->assertViewHas('studentsCount', 1);

        // Bypass the controller: insert directly, so only the cache can explain the stale count.
        User::factory()->student($classroom)->create();
        $this->assertSame(1, cache('admin_dashboard_stats')['studentsCount'] ?? null);

        $this->admin()->get(route('admin.dashboard'))->assertViewHas('studentsCount', 1);
    }

    public function test_store_student_invalidates_the_dashboard_cache(): void
    {
        $classroom = Classroom::factory()->create();

        $this->admin()->get(route('admin.dashboard'))->assertOk();
        $this->assertNotNull(cache('admin_dashboard_stats'));

        $this->admin()->post(route('admin.students.store'), [
            'nim' => '312010001', 'name' => 'Budi', 'classroom_id' => $classroom->id,
        ])->assertRedirect(route('admin.students.index'));

        $this->assertNull(cache('admin_dashboard_stats'));
    }

    public function test_analytics_renders_with_classroom_and_exam_metrics(): void
    {
        $classroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);
        [$course, $module] = $this->makeCourseWithModule();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        ExamSession::factory()->finished(80)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);

        $this->admin()->get(route('admin.analytics'))
            ->assertOk()
            ->assertViewIs('admin.analytics')
            ->assertViewHas('dates', fn ($dates) => $dates->count() === 30)
            ->assertViewHas('classroomScores', fn ($scores) => $scores->first()->avg_score === 80.0);
    }

    // ---------------------------------------------------------------- students index

    public function test_students_index_lists_only_students(): void
    {
        $classroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);
        $this->makeAdmin();

        $this->admin()->get(route('admin.students.index'))
            ->assertOk()
            ->assertViewIs('admin.students.index')
            ->assertSee($student->name)
            ->assertViewHas('students', fn ($students) => $students->count() === 1);
    }

    public function test_students_index_searches_by_name_and_nim(): void
    {
        $classroom = Classroom::factory()->create();
        $match = $this->makeStudent($classroom);
        $match->update(['name' => 'Siti Aminah']);
        $other = $this->makeStudent($classroom);
        $other->update(['name' => 'Budi Santoso']);

        $this->admin()->get(route('admin.students.index', ['search' => 'Siti']))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertDontSee('Budi Santoso');

        $this->admin()->get(route('admin.students.index', ['search' => $match->username]))
            ->assertOk()
            ->assertSee('Siti Aminah');
    }

    public function test_students_index_filters_by_classroom(): void
    {
        $classA = Classroom::factory()->create();
        $classB = Classroom::factory()->create();
        $inA = $this->makeStudent($classA);
        $inA->update(['name' => 'Dari Kelas A']);
        $inB = $this->makeStudent($classB);
        $inB->update(['name' => 'Dari Kelas B']);

        $this->admin()->get(route('admin.students.index', ['classroom_id' => $classA->id]))
            ->assertOk()
            ->assertSee('Dari Kelas A')
            ->assertDontSee('Dari Kelas B');
    }

    public function test_students_index_returns_json_in_ajax_mode(): void
    {
        $classroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);

        $this->admin()->get(route('admin.students.index', ['ajax' => 1]))
            ->assertOk()
            ->assertJsonStructure([['id', 'username', 'name', 'classroom_id']])
            ->assertJsonFragment(['username' => $student->username]);
    }

    public function test_create_student_renders_the_form(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'TI.22.A.1']);

        $this->admin()->get(route('admin.students.create'))
            ->assertOk()
            ->assertViewIs('admin.students.create')
            ->assertSee('TI.22.A.1');
    }

    // ---------------------------------------------------------------- students CRUD

    public function test_store_student_creates_account_with_nim_as_password(): void
    {
        $classroom = Classroom::factory()->create();

        $this->admin()->post(route('admin.students.store'), [
            'nim' => '312010001',
            'name' => 'Budi Santoso',
            'classroom_id' => $classroom->id,
        ])->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('success');

        $student = User::where('username', '312010001')->first();
        $this->assertNotNull($student);
        $this->assertSame('mahasiswa', $student->role);
        $this->assertSame($classroom->id, $student->classroom_id);
        // Default password equals the NIM.
        $this->assertTrue(Hash::check('312010001', $student->password));
    }

    public function test_store_student_validates_required_fields_and_nim_uniqueness(): void
    {
        $classroom = Classroom::factory()->create();
        User::factory()->create(['username' => '312010009']);

        $this->admin()->post(route('admin.students.store'), [])
            ->assertSessionHasErrors(['nim', 'name', 'classroom_id']);

        $this->admin()->post(route('admin.students.store'), [
            'nim' => '312010009', 'name' => 'Duplikat', 'classroom_id' => $classroom->id,
        ])->assertSessionHasErrors('nim');
    }

    public function test_edit_student_renders_the_form(): void
    {
        $classroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);

        $this->admin()->get(route('admin.students.edit', $student))
            ->assertOk()
            ->assertViewIs('admin.students.edit')
            ->assertViewHas('user', fn ($user) => $user->id === $student->id);
    }

    public function test_edit_student_is_forbidden_for_admin_accounts(): void
    {
        $victim = $this->makeAdmin();

        $this->admin()->get(route('admin.students.edit', $victim))->assertForbidden();
    }

    public function test_update_student_changes_name_nim_and_classroom(): void
    {
        $classroom = Classroom::factory()->create();
        $newClassroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);

        $this->admin()->put(route('admin.students.update', $student), [
            'nim' => '312010055', 'name' => 'Nama Baru', 'classroom_id' => $newClassroom->id,
        ])->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertSame('312010055', $student->username);
        $this->assertSame('Nama Baru', $student->name);
        $this->assertSame($newClassroom->id, $student->classroom_id);
    }

    public function test_update_student_allows_keeping_the_same_nim(): void
    {
        $classroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);
        $student->update(['username' => '312010077']);

        $this->admin()->put(route('admin.students.update', $student), [
            'nim' => '312010077', 'name' => 'Nama Baru', 'classroom_id' => $classroom->id,
        ])->assertRedirect(route('admin.students.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_update_student_is_forbidden_for_admin_accounts(): void
    {
        $classroom = Classroom::factory()->create();
        $victim = $this->makeAdmin();

        $this->admin()->put(route('admin.students.update', $victim), [
            'nim' => 'hacked', 'name' => 'Hacked', 'classroom_id' => $classroom->id,
        ])->assertForbidden();

        $this->assertSame('admin', $victim->fresh()->role);
    }

    public function test_reset_password_sets_the_password_back_to_the_nim(): void
    {
        $classroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);
        $student->update(['username' => '312010088', 'password' => Hash::make('something-else')]);

        $this->admin()->post(route('admin.students.reset_password', $student))
            ->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('312010088', $student->fresh()->password));
    }

    public function test_reset_password_is_forbidden_for_admin_accounts(): void
    {
        $victim = $this->makeAdmin();
        $before = $victim->password;

        $this->admin()->post(route('admin.students.reset_password', $victim))->assertForbidden();

        $this->assertSame($before, $victim->fresh()->password);
    }

    public function test_destroy_student_deletes_the_account(): void
    {
        $classroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);

        $this->admin()->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
    }

    public function test_destroy_student_keeps_exam_history_for_the_archive(): void
    {
        $classroom = Classroom::factory()->create();
        [, $module] = $this->makeCourseWithModule();
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        $session = ExamSession::factory()->finished(80)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);

        $response = $this->admin()->delete(route('admin.students.destroy', $student));

        $response->assertRedirect(route('admin.students.index'));
        // The flash message tells the admin the exam data was kept.
        $response->assertSessionHas('success');
        $this->assertStringContainsString('arsip', session('success'));

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        // History survives via nullOnDelete.
        $this->assertNull($session->fresh()->user_id);
    }

    public function test_destroy_student_is_forbidden_for_admin_accounts(): void
    {
        $victim = $this->makeAdmin();

        $this->admin()->delete(route('admin.students.destroy', $victim))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }

    public function test_bulk_delete_removes_selected_students_only(): void
    {
        $classroom = Classroom::factory()->create();
        $keep = $this->makeStudent($classroom);
        $remove1 = $this->makeStudent($classroom);
        $remove2 = $this->makeStudent($classroom);

        $this->admin()->post(route('admin.students.bulk_delete'), [
            'ids' => [$remove1->id, $remove2->id],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $keep->id]);
        $this->assertDatabaseMissing('users', ['id' => $remove1->id]);
        $this->assertDatabaseMissing('users', ['id' => $remove2->id]);
    }

    public function test_bulk_delete_never_removes_admin_accounts(): void
    {
        $classroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);
        $admin = $this->makeAdmin();

        $this->admin()->post(route('admin.students.bulk_delete'), [
            'ids' => [$student->id, $admin->id],
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_bulk_delete_with_empty_selection_reports_an_error(): void
    {
        $this->admin()->post(route('admin.students.bulk_delete'), ['ids' => []])
            ->assertSessionHas('error');
    }

    // ---------------------------------------------------------------- export / import

    public function test_export_students_streams_csv_with_nim_name_and_classroom(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'TI.22.A.1']);
        $student = $this->makeStudent($classroom);
        $student->update(['username' => '312010001', 'name' => 'Budi Santoso']);

        $response = $this->admin()->get(route('admin.students.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('NIM', $csv);
        $this->assertStringContainsString('312010001', $csv);
        $this->assertStringContainsString('Budi Santoso', $csv);
        $this->assertStringContainsString('TI.22.A.1', $csv);
    }

    public function test_export_students_filters_by_classroom(): void
    {
        $classA = Classroom::factory()->create();
        $classB = Classroom::factory()->create();
        $a = $this->makeStudent($classA);
        $a->update(['name' => 'Murid Kelas A']);
        $b = $this->makeStudent($classB);
        $b->update(['name' => 'Murid Kelas B']);

        $response = $this->admin()->get(route('admin.students.export', ['classroom_id' => $classA->id]));
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Murid Kelas A', $csv);
        $this->assertStringNotContainsString('Murid Kelas B', $csv);
    }

    public function test_export_students_sanitizes_formula_injection(): void
    {
        $classroom = Classroom::factory()->create();
        $student = $this->makeStudent($classroom);
        $student->update(['name' => '=1+1', 'username' => '@SUM(A1)']);

        $response = $this->admin()->get(route('admin.students.export'));
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString("'@SUM(A1)", $csv);
    }

    public function test_download_student_template_returns_example_csv(): void
    {
        $response = $this->admin()->get(route('admin.template_students'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('NIM', $csv);
        $this->assertStringContainsString('Kelas', $csv);
        $this->assertStringContainsString('John Doe', $csv);
    }

    public function test_download_question_template_returns_eight_columns(): void
    {
        $response = $this->admin()->get(route('admin.template_questions'));

        $response->assertOk();

        $csv = $response->streamedContent();
        foreach (['Soal', 'Gambar', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Kunci Jawaban', 'Kategori'] as $column) {
            $this->assertStringContainsString($column, $csv);
        }
    }

    public function test_import_students_creates_users_and_classrooms(): void
    {
        $csv = "NIM,Nama,Kelas\r\n312010001,Budi Santoso,TI.22.A.1\r\n312010002,Siti Aminah,TI.22.A.1\r\n";

        $this->admin()->post(route('admin.import_students'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('classrooms', ['name' => 'TI.22.A.1']);
        $this->assertDatabaseHas('users', [
            'username' => '312010001', 'name' => 'Budi Santoso', 'role' => 'mahasiswa',
        ]);
        $this->assertDatabaseHas('users', ['username' => '312010002']);
    }

    public function test_import_students_sets_the_password_to_the_nim(): void
    {
        $csv = "NIM,Nama,Kelas\n312010003,Budi,TI.22.A.1\n";

        $this->admin()->post(route('admin.import_students'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);

        $student = User::where('username', '312010003')->first();
        $this->assertTrue(Hash::check('312010003', $student->password));
    }

    public function test_import_students_skips_duplicate_nims(): void
    {
        User::factory()->create(['username' => '312010001']);
        $csv = "NIM,Nama,Kelas\n312010001,Sudah Ada,TI.22.A.1\n312010004,Baru,TI.22.A.1\n";

        $this->admin()->post(route('admin.import_students'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertStringContainsString('duplikat', session('success'));

        // existing NIM untouched, the new one imported (the acting admin is the third row).
        $this->assertDatabaseMissing('users', ['username' => '312010001', 'name' => 'Sudah Ada']);
        $this->assertDatabaseHas('users', ['username' => '312010004', 'name' => 'Baru']);
    }

    public function test_import_students_handles_a_utf8_bom_header(): void
    {
        $csv = "\xEF\xBB\xBFNIM,Nama,Kelas\n312010005,Budi,TI.22.A.1\n";

        $this->admin()->post(route('admin.import_students'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['username' => '312010005']);
    }

    public function test_import_students_warns_about_automatically_created_classrooms(): void
    {
        $csv = "NIM,Nama,Kelas\n312010006,Budi,Kelas Baru\n";

        $this->admin()->post(route('admin.import_students'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('success');

        $message = session('success');
        $this->assertStringContainsString('Kelas baru dibuat otomatis', $message);
        $this->assertStringContainsString('Kelas Baru', $message);
    }

    public function test_import_students_ignores_rows_with_too_few_columns(): void
    {
        $csv = "NIM,Nama,Kelas\n312010007,Budi\n312010008,Siti,TI.22.A.2\n";

        $this->admin()->post(route('admin.import_students'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['username' => '312010007']);
        $this->assertDatabaseHas('users', ['username' => '312010008']);
    }

    public function test_import_students_rejects_an_empty_selection(): void
    {
        $this->admin()->post(route('admin.import_students'), [])
            ->assertSessionHasErrors('csv_file');
    }

    public function test_import_students_rejects_a_non_csv_file(): void
    {
        $this->admin()->post(route('admin.import_students'), [
            'csv_file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('csv_file');
    }

    // ---------------------------------------------------------------- classrooms

    public function test_classrooms_index_lists_classes_with_student_counts(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'TI.22.A.1']);
        $this->makeStudent($classroom);
        $this->makeStudent($classroom);

        $this->admin()->get(route('admin.classrooms'))
            ->assertOk()
            ->assertViewIs('admin.classrooms')
            ->assertSee('TI.22.A.1')
            ->assertViewHas('classrooms', fn ($classrooms) => $classrooms->first()->users_count === 2);
    }

    public function test_classrooms_index_searches_by_name(): void
    {
        Classroom::factory()->create(['name' => 'TI.22.A.1']);
        Classroom::factory()->create(['name' => 'SI.22.B.2']);

        $this->admin()->get(route('admin.classrooms', ['search' => 'A.1']))
            ->assertOk()
            ->assertSee('TI.22.A.1')
            ->assertDontSee('SI.22.B.2');
    }

    public function test_store_classroom_creates_a_class(): void
    {
        $this->admin()->post(route('admin.classrooms.store'), [
            'name' => 'TI.22.A.1', 'academic_year' => '2025/2026', 'semester' => 'Genap',
        ])->assertRedirect(route('admin.classrooms'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('classrooms', [
            'name' => 'TI.22.A.1', 'academic_year' => '2025/2026', 'semester' => 'Genap',
        ]);
    }

    public function test_store_classroom_validates_name_uniqueness_and_semester(): void
    {
        Classroom::factory()->create(['name' => 'TI.22.A.1']);

        $this->admin()->post(route('admin.classrooms.store'), ['name' => 'TI.22.A.1'])
            ->assertSessionHasErrors('name');

        $this->admin()->post(route('admin.classrooms.store'), [
            'name' => 'TI.22.B.2', 'semester' => 'Libur',
        ])->assertSessionHasErrors('semester');
    }

    public function test_update_classroom_changes_fields(): void
    {
        $classroom = Classroom::factory()->create();

        $this->admin()->put(route('admin.classrooms.update', $classroom), [
            'name' => 'TI.23.A.1', 'academic_year' => '2026/2027', 'semester' => 'Ganjil',
        ])->assertRedirect(route('admin.classrooms'))
            ->assertSessionHas('success');

        $classroom->refresh();
        $this->assertSame('TI.23.A.1', $classroom->name);
        $this->assertSame('Ganjil', $classroom->semester);
    }

    public function test_update_classroom_allows_keeping_its_own_name(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'TI.22.A.1']);

        $this->admin()->put(route('admin.classrooms.update', $classroom), [
            'name' => 'TI.22.A.1', 'semester' => 'Genap',
        ])->assertRedirect(route('admin.classrooms'))
            ->assertSessionHasNoErrors();
    }

    public function test_destroy_classroom_deletes_an_empty_class(): void
    {
        $classroom = Classroom::factory()->create();

        $this->admin()->delete(route('admin.classrooms.destroy', $classroom))
            ->assertRedirect(route('admin.classrooms'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('classrooms', ['id' => $classroom->id]);
    }

    public function test_destroy_classroom_is_blocked_when_students_are_enrolled(): void
    {
        $classroom = Classroom::factory()->create();
        $this->makeStudent($classroom);

        $this->admin()->delete(route('admin.classrooms.destroy', $classroom))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('classrooms', ['id' => $classroom->id]);
    }

    // ---------------------------------------------------------------- move students

    public function test_move_students_form_renders(): void
    {
        Classroom::factory()->create(['name' => 'TI.22.A.1']);

        $this->admin()->get(route('admin.students.move.form'))
            ->assertOk()
            ->assertViewIs('admin.students.move')
            ->assertSee('TI.22.A.1');
    }

    public function test_move_students_transfers_selected_students(): void
    {
        $from = Classroom::factory()->create();
        $to = Classroom::factory()->create();
        $moving1 = $this->makeStudent($from);
        $moving2 = $this->makeStudent($from);
        $staying = $this->makeStudent($from);

        $this->admin()->post(route('admin.students.move'), [
            'from_classroom_id' => $from->id,
            'to_classroom_id' => $to->id,
            'student_ids' => [$moving1->id, $moving2->id],
        ])->assertRedirect(route('admin.classrooms'))
            ->assertSessionHas('success');

        $this->assertSame($to->id, $moving1->fresh()->classroom_id);
        $this->assertSame($to->id, $moving2->fresh()->classroom_id);
        $this->assertSame($from->id, $staying->fresh()->classroom_id);
    }

    public function test_move_students_ignores_students_not_in_the_source_class(): void
    {
        $from = Classroom::factory()->create();
        $to = Classroom::factory()->create();
        $other = Classroom::factory()->create();
        $stranger = $this->makeStudent($other);

        $this->admin()->post(route('admin.students.move'), [
            'from_classroom_id' => $from->id,
            'to_classroom_id' => $to->id,
            'student_ids' => [$stranger->id],
        ])->assertRedirect(route('admin.classrooms'));

        $this->assertSame($other->id, $stranger->fresh()->classroom_id);
    }

    public function test_move_students_rejects_moving_to_the_same_classroom(): void
    {
        $class = Classroom::factory()->create();
        $student = $this->makeStudent($class);

        $this->admin()->post(route('admin.students.move'), [
            'from_classroom_id' => $class->id,
            'to_classroom_id' => $class->id,
            'student_ids' => [$student->id],
        ])->assertSessionHasErrors('from_classroom_id');
    }

    public function test_move_students_requires_at_least_one_selection(): void
    {
        $from = Classroom::factory()->create();
        $to = Classroom::factory()->create();

        $this->admin()->post(route('admin.students.move'), [
            'from_classroom_id' => $from->id,
            'to_classroom_id' => $to->id,
            'student_ids' => [],
        ])->assertSessionHasErrors('student_ids');
    }

    // ---------------------------------------------------------------- recap

    public function test_classroom_recap_builds_a_score_matrix(): void
    {
        $classroom = Classroom::factory()->create();
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        ExamSession::factory()->finished(85)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $this->admin()->get(route('admin.classrooms.recap', $classroom))
            ->assertOk()
            ->assertViewIs('admin.classrooms.recap')
            ->assertViewHas('exams', fn ($exams) => $exams->count() === 1)
            ->assertViewHas('students', fn ($students) => $students->count() === 1)
            ->assertViewHas('lastAttempts', fn ($attempts) => $attempts->count() === 1);
    }

    public function test_classroom_recap_csv_includes_exam_columns_and_average(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'TI.22.A.1']);
        [$course, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $exam->update(['title' => 'Ujian Modul Satu']);
        $student = $this->makeStudent($classroom);
        $student->update(['username' => '312010001', 'name' => 'Budi Santoso']);

        ExamSession::factory()->finished(80)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1,
        ]);

        $response = $this->admin()->get(route('admin.classrooms.recap.csv', $classroom));

        $response->assertOk();
        $this->assertStringContainsString('rekap_kelas_TI.22.A.1.csv', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Ujian Modul Satu', $csv);
        $this->assertStringContainsString('312010001', $csv);
        $this->assertStringContainsString('Budi Santoso', $csv);
        $this->assertStringContainsString('Rata-rata', $csv);
        $this->assertStringContainsString('80', $csv);
    }

    public function test_classroom_recap_csv_uses_the_last_finished_attempt(): void
    {
        $classroom = Classroom::factory()->create();
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        ExamSession::factory()->finished(30)->attempt(1)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);
        ExamSession::factory()->finished(90)->attempt(2)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);

        $response = $this->admin()->get(route('admin.classrooms.recap.csv', $classroom));
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('90', $csv);
        $this->assertStringNotContainsString('30', $csv);
    }

    public function test_classroom_recap_csv_marks_unfinished_sessions_as_dash(): void
    {
        $classroom = Classroom::factory()->create();
        [, $module] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);

        ExamSession::factory()->create(['user_id' => $student->id, 'exam_id' => $exam->id]);

        $response = $this->admin()->get(route('admin.classrooms.recap.csv', $classroom));
        $response->assertOk();

        $this->assertStringContainsString('-', $response->streamedContent());
    }

    public function test_classroom_recap_csv_sanitizes_the_filename(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'IF/A"1']);
        [, $module] = $this->makeCourseWithModule(1);
        $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);

        $response = $this->admin()->get(route('admin.classrooms.recap.csv', $classroom));
        $response->assertOk();

        // Every character outside [A-Za-z0-9_-.] is replaced, so the raw quote cannot survive.
        $this->assertStringContainsString('filename="rekap_kelas_IF_A_1.csv"', $response->headers->get('content-disposition'));
    }

    public function test_classroom_recap_sanitizes_student_names_in_csv(): void
    {
        $classroom = Classroom::factory()->create();
        [, $module] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $classroom, ['start_time' => now()]);
        $student = $this->makeStudent($classroom);
        $student->update(['name' => '+malicious']);

        ExamSession::factory()->finished(90)->create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
        ]);

        $response = $this->admin()->get(route('admin.classrooms.recap.csv', $classroom));
        $response->assertOk();

        $this->assertStringContainsString("'+malicious", $response->streamedContent());
    }

    // ---------------------------------------------------------------- access

    public function test_students_cannot_reach_admin_pages(): void
    {
        $student = $this->makeStudent(Classroom::factory()->create());

        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.analytics'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.students.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.classrooms'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.students.bulk_delete'), ['ids' => [1]])->assertForbidden();
    }

    public function test_guests_cannot_reach_admin_pages(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.analytics'))->assertRedirect(route('login'));
        $this->get(route('admin.students.index'))->assertRedirect(route('login'));
    }
}
