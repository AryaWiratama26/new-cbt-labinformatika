<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Module;
use App\Models\Option;
use App\Models\Question;
use App\Services\QuestionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class ModuleControllerTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private function admin()
    {
        return $this->actingAs($this->makeAdmin());
    }

    /* ------------------------------------------------------------------
     | Authorization
     | ----------------------------------------------------------------- */

    public function test_guests_cannot_reach_module_pages(): void
    {
        $course = Course::factory()->create();

        $this->get(route('admin.courses.modules.index', $course))->assertRedirect(route('login'));
        $this->get(route('admin.courses.modules.create', $course))->assertRedirect(route('login'));
    }

    public function test_students_cannot_manage_modules(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->makeStudent())->get(route('admin.courses.modules.index', $course))->assertForbidden();
    }

    /* ------------------------------------------------------------------
     | Module CRUD
     | ----------------------------------------------------------------- */

    public function test_index_lists_modules_with_question_counts(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create(['name' => 'Aljabar Linear']);
        $this->makeQuestionWithOptions($module);

        $response = $this->admin()->get(route('admin.courses.modules.index', $course));

        $response->assertOk();
        $response->assertSee('Aljabar Linear');
        $response->assertSee($course->name);
        $this->assertSame(1, $module->questions()->count());
    }

    public function test_create_page_renders(): void
    {
        $course = Course::factory()->create();

        $this->admin()->get(route('admin.courses.modules.create', $course))->assertOk();
    }

    public function test_store_creates_module(): void
    {
        $course = Course::factory()->create();

        $this->admin()->post(route('admin.courses.modules.store', $course), [
            'name' => 'Limit dan Turunan',
            'module_number' => '1',
            'description' => 'Pembderivan fungsi',
        ])->assertRedirect(route('admin.courses.modules.index', $course));

        $this->assertDatabaseHas('modules', [
            'course_id' => $course->id,
            'name' => 'Limit dan Turunan',
            'module_number' => '1',
            'description' => 'Pembderivan fungsi',
        ]);
    }

    public function test_store_accepts_module_without_optional_fields(): void
    {
        $course = Course::factory()->create();

        $this->admin()->post(route('admin.courses.modules.store', $course), [
            'name' => 'Tanpa Nomor',
        ])->assertSessionHasNoErrors();

        $module = Module::where('name', 'Tanpa Nomor')->firstOrFail();
        $this->assertNull($module->module_number);
    }

    public function test_store_requires_name(): void
    {
        $course = Course::factory()->create();

        $this->admin()->post(route('admin.courses.modules.store', $course), [
            'name' => '',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('modules', 0);
    }

    public function test_show_paginates_questions_fifteen_per_page(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        foreach (range(1, 17) as $i) {
            $this->makeQuestionWithOptions($module, 0, "Soal nomor {$i}");
        }

        $response = $this->admin()->get(route('admin.courses.modules.show', [$course, $module]));

        $response->assertOk();
        $this->assertCount(15, $response->viewData('questions')->items());
        $this->assertSame(17, $response->viewData('questions')->total());
    }

    public function test_show_filters_questions_by_search_and_category(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $target = $this->makeQuestionWithOptions($module, 0, 'Teorema_limit_dasar');
        $target->update(['category' => 'sulit']);

        $other = $this->makeQuestionWithOptions($module, 0, 'Soal deret biasa');
        $other->update(['category' => 'mudah']);

        $bySearch = $this->admin()->get(route('admin.courses.modules.show', [$course, $module]).'?search=limit');
        $this->assertSame(['Teorema_limit_dasar'], $bySearch->viewData('questions')->pluck('content')->all());

        $byCategory = $this->admin()->get(route('admin.courses.modules.show', [$course, $module]).'?category=sulit');
        $this->assertSame(['Teorema_limit_dasar'], $byCategory->viewData('questions')->pluck('content')->all());
    }

    public function test_show_keeps_filters_in_pagination_links(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        foreach (range(1, 16) as $i) {
            $this->makeQuestionWithOptions($module, 0, "Soal {$i}");
        }
        $module->questions()->update(['category' => 'sedang']);

        $response = $this->admin()->get(route('admin.courses.modules.show', [$course, $module]).'?category=sedang');

        $response->assertOk();
        $response->assertSee('category=sedang');
    }

    /* ------------------------------------------------------------------
     | Module deletion
     | ----------------------------------------------------------------- */

    public function test_destroy_deletes_module_and_its_unclaimed_questions(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);

        $this->admin()->delete(route('admin.courses.modules.destroy', [$course, $module]))
            ->assertRedirect(route('admin.courses.modules.index', $course));

        $this->assertDatabaseMissing('modules', ['id' => $module->id]);

        // No exam claims the question, so it is removed together with its options
        // instead of being left behind as an orphan row.
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseCount('options', 0);
    }

    public function test_destroy_reassigns_exam_questions_before_cascade(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $exam = Exam::factory()->create(['course_id' => $course->id, 'module_id' => $module->id]);

        $question = $this->makeQuestionWithOptions($module);
        $this->assertNull($question->exam_id);

        $this->admin()->delete(route('admin.courses.modules.destroy', [$course, $module]));

        $this->assertDatabaseMissing('modules', ['id' => $module->id]);
        // Question survives and is now owned by the exam instead of the removed module.
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'exam_id' => $exam->id]);
    }

    public function test_destroy_spreads_shared_questions_across_every_exam(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $first = Exam::factory()->create(['course_id' => $course->id, 'module_id' => $module->id]);
        $second = Exam::factory()->create(['course_id' => $course->id, 'module_id' => $module->id]);

        $questions = collect(range(1, 4))
            ->map(fn () => $this->makeQuestionWithOptions($module));

        $this->admin()->delete(route('admin.courses.modules.destroy', [$course, $module]));

        $this->assertDatabaseMissing('modules', ['id' => $module->id]);

        // No question may be lost, and neither exam may be left empty.
        $this->assertDatabaseCount('questions', 4);
        $this->assertSame(0, $questions->filter(fn ($q) => $q->refresh()->exam_id === null)->count());
        $this->assertSame(2, Question::where('exam_id', $first->id)->count());
        $this->assertSame(2, Question::where('exam_id', $second->id)->count());
    }

    public function test_destroy_keeps_questions_already_owned_by_an_exam(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $exam = Exam::factory()->create(['course_id' => $course->id, 'module_id' => $module->id]);

        $owned = $this->makeQuestionWithOptions($module);
        $owned->update(['exam_id' => $exam->id]);
        $fresh = $this->makeQuestionWithOptions($module);

        $this->admin()->delete(route('admin.courses.modules.destroy', [$course, $module]));

        // The already-owned question keeps its original exam; the unowned one is re-homed.
        $this->assertSame($exam->id, $owned->refresh()->exam_id);
        $this->assertSame($exam->id, $fresh->refresh()->exam_id);
        $this->assertDatabaseCount('questions', 2);
    }

    public function test_destroy_deletes_attached_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('questions/gambar.png', 'binary');

        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);
        $question->update(['image' => 'questions/gambar.png']);

        $this->admin()->delete(route('admin.courses.modules.destroy', [$course, $module]));

        Storage::disk('public')->assertMissing('questions/gambar.png');
    }

    public function test_destroy_removes_question_with_external_image_without_touching_storage(): void
    {
        Storage::fake('public');

        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);
        $question->update(['image' => 'https://cdn.example.com/remote.png']);

        $this->admin()->delete(route('admin.courses.modules.destroy', [$course, $module]))->assertRedirect();

        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    /* ------------------------------------------------------------------
     | Question creation
     | ----------------------------------------------------------------- */

    public function test_create_question_page_renders(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $this->admin()->get(route('admin.courses.modules.questions.create', [$course, $module]))->assertOk();
    }

    public function test_store_question_creates_four_options_and_marks_correct_one(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $this->admin()->post(route('admin.courses.modules.questions.store', [$course, $module]), [
            'content' => 'Berapakah hasil 7 x 8?',
            'options' => ['54', '56', '58', '60'],
            'correct_option' => 1,
            'category' => 'sedang',
            'explanation' => '7 x 8 = 56',
        ])->assertRedirect(route('admin.courses.modules.show', [$course, $module]));

        $question = Question::where('content', 'Berapakah hasil 7 x 8?')->firstOrFail();

        $this->assertSame($module->id, $question->module_id);
        $this->assertSame('sedang', $question->category);
        $this->assertSame('7 x 8 = 56', $question->explanation);

        $options = $question->options()->orderBy('id')->get();
        $this->assertCount(4, $options);
        $this->assertSame(['54', '56', '58', '60'], $options->pluck('content')->all());
        $this->assertSame([false, true, false, false], $options->pluck('is_correct')->map(fn ($v) => (bool) $v)->all());
    }

    public function test_store_question_uploads_image_to_public_disk(): void
    {
        Storage::fake('public');

        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $this->admin()->post(route('admin.courses.modules.questions.store', [$course, $module]), [
            'content' => 'Soal bergambar',
            'image' => UploadedFile::fake()->image('diagram.png'),
            'options' => ['a', 'b', 'c', 'd'],
            'correct_option' => 0,
        ])->assertSessionHasNoErrors();

        $question = Question::where('content', 'Soal bergambar')->firstOrFail();
        $this->assertNotNull($question->image);
        Storage::disk('public')->assertExists($question->image);
    }

    public function test_store_question_validates_payload(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $this->admin()->post(route('admin.courses.modules.questions.store', [$course, $module]), [
            'content' => '',
            'options' => ['a', 'b'],
            'correct_option' => 9,
            'category' => 'gila',
        ])->assertSessionHasErrors(['content', 'options', 'correct_option', 'category']);

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_store_question_rejects_non_image_upload(): void
    {
        Storage::fake('public');

        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $this->admin()->post(route('admin.courses.modules.questions.store', [$course, $module]), [
            'content' => 'Soal dengan file(aws)',
            'image' => UploadedFile::fake()->create('virus.pdf', 10),
            'options' => ['a', 'b', 'c', 'd'],
            'correct_option' => 0,
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('questions', 0);
    }

    /* ------------------------------------------------------------------
     | Question update / duplicate / delete
     | ----------------------------------------------------------------- */

    public function test_edit_question_page_loads_options(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);

        $response = $this->admin()->get(route('admin.courses.modules.questions.edit', [$course, $module, $question]));

        $response->assertOk();
        $this->assertCount(4, $response->viewData('question')->options);
    }

    public function test_update_question_updates_options_in_place(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module, 0);
        $originalOptionIds = $question->options()->orderBy('id')->pluck('id')->all();

        $this->admin()->put(route('admin.courses.modules.questions.update', [$course, $module, $question]), [
            'content' => 'Per修订 soal',
            'options' => ['Satu', 'Dua', 'Tiga', 'Empat'],
            'correct_option' => 3,
            'category' => 'sulit',
        ])->assertRedirect(route('admin.courses.modules.show', [$course, $module]));

        $question->refresh();
        $this->assertSame('Per修订 soal', $question->content);
        $this->assertSame('sulit', $question->category);

        $options = $question->options()->orderBy('id')->get();
        $this->assertCount(4, $options);
        // Option IDs must be preserved so existing student answers keep pointing at valid rows.
        $this->assertSame($originalOptionIds, $options->pluck('id')->all());
        $this->assertSame(['Satu', 'Dua', 'Tiga', 'Empat'], $options->pluck('content')->all());
        $this->assertSame([false, false, false, true], $options->pluck('is_correct')->map(fn ($v) => (bool) $v)->all());
    }

    public function test_update_question_replaces_image_and_deletes_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('questions/lama.png', 'old');

        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);
        $question->update(['image' => 'questions/lama.png']);

        $this->admin()->put(route('admin.courses.modules.questions.update', [$course, $module, $question]), [
            'content' => $question->content,
            'image' => UploadedFile::fake()->image('baru.png'),
            'options' => ['a', 'b', 'c', 'd'],
            'correct_option' => 0,
        ])->assertSessionHasNoErrors();

        $newPath = $question->refresh()->image;
        $this->assertNotSame('questions/lama.png', $newPath);
        Storage::disk('public')->assertMissing('questions/lama.png');
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_update_question_can_remove_image_without_upload(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('questions/hapus.png', 'old');

        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);
        $question->update(['image' => 'questions/hapus.png']);

        $this->admin()->put(route('admin.courses.modules.questions.update', [$course, $module, $question]), [
            'content' => $question->content,
            'remove_image' => '1',
            'options' => ['a', 'b', 'c', 'd'],
            'correct_option' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertNull($question->refresh()->image);
        Storage::disk('public')->assertMissing('questions/hapus.png');
    }

    public function test_update_question_keeps_image_when_nothing_image_related_is_sent(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('questions/keep.png', 'old');

        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);
        $question->update(['image' => 'questions/keep.png']);

        $this->admin()->put(route('admin.courses.modules.questions.update', [$course, $module, $question]), [
            'content' => 'Diperbarui tanpa gambar',
            'options' => ['a', 'b', 'c', 'd'],
            'correct_option' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame('questions/keep.png', $question->refresh()->image);
        Storage::disk('public')->assertExists('questions/keep.png');
    }

    public function test_update_question_deletes_surplus_options(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);
        $this->assertCount(4, $question->options);

        $this->admin()->put(route('admin.courses.modules.questions.update', [$course, $module, $question]), [
            'content' => $question->content,
            'options' => ['a', 'b', 'c', 'd'],
            'correct_option' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertCount(4, $question->options()->get());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
    }

    public function test_duplicate_question_copies_options_to_the_same_module(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module, 2, 'Soal asli');

        $this->admin()->post(route('admin.courses.modules.questions.duplicate', [$course, $module, $question]))
            ->assertRedirect(route('admin.courses.modules.show', [$course, $module]));

        $this->assertDatabaseCount('questions', 2);

        $copy = Question::where('id', '!=', $question->id)->firstOrFail();
        $this->assertSame('Soal asli (copy)', $copy->content);
        $this->assertSame($module->id, $copy->module_id);
        $this->assertSame($question->category, $copy->category);
        $this->assertSame(
            $question->options()->orderBy('content')->pluck('content')->all(),
            $copy->options()->orderBy('content')->pluck('content')->all()
        );
        $this->assertSame(1, $copy->options()->where('is_correct', true)->count());
    }

    public function test_duplicate_question_copies_image_file_instead_of_sharing_it(): void
    {
        // duplicateQuestion() reads/writes through storage_path() directly, not the
        // Storage facade, so the fixture must live on the real filesystem.
        $dir = storage_path('app/public/questions');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $source = 'questions/'.$this->uniqueFileName('asli.png');
        file_put_contents(storage_path('app/public/'.$source), 'binary');

        try {
            $course = Course::factory()->create();
            $module = Module::factory()->for($course)->create();
            $question = $this->makeQuestionWithOptions($module);
            $question->update(['image' => $source]);

            $this->admin()->post(route('admin.courses.modules.questions.duplicate', [$course, $module, $question]))
                ->assertSessionHasNoErrors();

            $copy = Question::where('id', '!=', $question->id)->firstOrFail();
            $this->assertNotNull($copy->image);
            $this->assertNotSame($source, $copy->image, 'Duplicate must own its own image file.');
            $this->assertStringStartsWith('questions/dup_', $copy->image);
            $this->assertFileExists(storage_path('app/public/'.$copy->image));
            $this->assertFileExists(storage_path('app/public/'.$source), 'Original image must survive duplication.');
        } finally {
            $this->deleteStorageFiles('test_', 'dup_');
        }
    }

    public function test_duplicate_question_drops_image_when_source_file_is_missing(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);
        $question->update(['image' => 'questions/hilang.png']);

        $this->admin()->post(route('admin.courses.modules.questions.duplicate', [$course, $module, $question]))
            ->assertSessionHasNoErrors()
            // The copy silently lost its picture before, so the admin is now warned
            // that the original file was missing.
            ->assertSessionHas('success', 'Soal berhasil digandakan. Perhatian: gambar soal asli tidak ditemukan, jadi duplikat dibuat tanpa gambar.');

        $copy = Question::where('id', '!=', $question->id)->firstOrFail();
        // The dangling path is not carried over; the copy has no image at all.
        $this->assertNull($copy->image);
        $this->assertSame($question->content.' (copy)', $copy->content);
    }

    public function test_duplicate_question_does_not_report_missing_image_when_there_is_none(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);

        $this->admin()->post(route('admin.courses.modules.questions.duplicate', [$course, $module, $question]))
            ->assertSessionHas('success', 'Soal berhasil digandakan.');

        $this->assertNull(Question::where('id', '!=', $question->id)->firstOrFail()->image);
    }

    public function test_destroy_question_deletes_image_and_options(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('questions/remove.png', 'binary');

        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);
        $question->update(['image' => 'questions/remove.png']);

        $this->admin()->delete(route('admin.courses.modules.questions.destroy', [$course, $module, $question]))
            ->assertRedirect(route('admin.courses.modules.show', [$course, $module]));

        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertDatabaseCount('options', 0);
        Storage::disk('public')->assertMissing('questions/remove.png');
    }

    public function test_destroy_question_keeps_files_outside_questions_folder(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/keep.png', 'binary');

        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();
        $question = $this->makeQuestionWithOptions($module);
        $question->update(['image' => 'uploads/keep.png']);

        $this->admin()->delete(route('admin.courses.modules.questions.destroy', [$course, $module, $question]))
            ->assertRedirect();

        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        Storage::disk('public')->assertExists('uploads/keep.png');
    }

    /* ------------------------------------------------------------------
     | CSV import
     | ----------------------------------------------------------------- */

    public function test_import_questions_reads_the_six_column_format(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $csv = "Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Kunci\n"
            ."SiapaIKA?,Soekarno,Soeharto,Habibie,Suharto,A\n"
            ."2+2=?,4,5,3,2,B\n";

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertSessionHas('success', '2 soal berhasil diimpor ke modul.');

        $this->assertDatabaseCount('questions', 2);

        $first = Question::where('content', 'SiapaIKA?')->firstOrFail();
        $this->assertNull($first->image);
        $this->assertNull($first->category);
        $this->assertSame('Soekarno', $first->options()->where('is_correct', true)->value('content'));
    }

    public function test_import_questions_reads_the_eight_column_format_with_image_and_category(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $csv = "Pertanyaan,Gambar,Opsi A,Opsi B,Opsi C,Opsi D,Kunci,Kategori\n"
            ."Gambar soal,gambar.png,Ya,Tidak,Mungkin,Mungkin,Ya,Mudah\n";

        try {
            $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
                'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
                'images_zip' => $this->makeZip(['gambar.png' => $this->pngBytes()]),
            ])->assertSessionHas('success', '1 soal berhasil diimpor ke modul.');

            $question = Question::where('content', 'Gambar soal')->firstOrFail();
            $this->assertSame('questions/gambar.png', $question->image);
        } finally {
            $this->deleteStorageFiles('gambar.png');
        }

        // Category is normalised to the lowercase slug, so the stored value always
        // matches the module filter and the coloured badge.
        $this->assertSame('mudah', $question->category);
        $this->assertCount(4, $question->options);
    }

    public function test_import_questions_drops_reference_to_an_image_that_never_landed(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $csv = "Pertanyaan,Gambar,Opsi A,Opsi B,Opsi C,Opsi D,Kunci,Kategori\n"
            ."Gambar tidak ada,gambar.png,A,B,C,D,A,mudah\n"
            ."Gambar ditolak,dokumen.pdf,A,B,C,D,A,mudah\n";

        try {
            // The archive contains no image the CSV points at, and the PDF is rejected
            // by the extension whitelist.
            $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
                'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
                'images_zip' => $this->makeZip(['dokumen.pdf' => '%PDF-1.4 fake']),
            ])->assertSessionHas(
                'success',
                '2 soal berhasil diimpor ke modul. 2 gambar tidak ditemukan pada arsip dan tidak dilampirkan.'
            );

            // Storing a path that can never resolve would render a broken image forever.
            $this->assertNull(Question::where('content', 'Gambar tidak ada')->firstOrFail()->image);
            $this->assertNull(Question::where('content', 'Gambar ditolak')->firstOrFail()->image);
        } finally {
            $this->deleteStorageFiles('gambar.png', 'dokumen.pdf');
        }
    }

    public function test_import_questions_keeps_both_files_when_two_folders_share_a_basename(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $png = $this->pngBytes();
        $jpeg = $this->jpegBytes();

        $csv = "Pertanyaan,Gambar,Opsi A,Opsi B,Opsi C,Opsi D,Kunci,Kategori\n"
            ."Gambar pertama,satu.png,A,B,C,D,A,mudah\n"
            ."Gambar kedua,dua.png,A,B,C,D,A,mudah\n";

        try {
            $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
                'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
                'images_zip' => $this->makeZip([
                    'folder1/satu.png' => $png,
                    'folder2/satu.png' => $jpeg,
                    'folder1/dua.png' => $jpeg,
                ]),
            ])->assertSessionHas('success');

            $this->assertSame('questions/satu.png', Question::where('content', 'Gambar pertama')->firstOrFail()->image);
            $this->assertSame('questions/dua.png', Question::where('content', 'Gambar kedua')->firstOrFail()->image);

            // folder2/satu.png collides with folder1/satu.png and must be renamed
            // instead of overwriting it.
            $files = glob(storage_path('app/public/questions/satu*.png')) ?: [];
            $this->assertCount(2, $files, 'Both archives must be written, not one overwriting the other.');
            $this->assertNotSame(
                md5_file($files[0]),
                md5_file($files[1]),
                'The two colliding images had different contents; both must survive.'
            );
        } finally {
            $this->deleteStorageFiles('satu', 'dua');
        }
    }

    public function test_import_questions_stores_lowercase_category_as_provided(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $csv = "Pertanyaan,Gambar,Opsi A,Opsi B,Opsi C,Opsi D,Kunci,Kategori\n"
            ."Kategori slug,,A,B,C,D,A,sulit\n";

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('questions', ['content' => 'Kategori slug', 'category' => 'sulit']);
    }

    public function test_import_questions_maps_synonyms_and_rejects_unknown_categories(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $csv = "Pertanyaan,Gambar,Opsi A,Opsi B,Opsi C,Opsi D,Kunci,Kategori\n"
            ."English easy,,A,B,C,D,A,easy\n"
            ."English hard,,A,B,C,D,A,hard\n"
            ."Uppercase suspect,,A,B,C,D,A,SULIT\n"
            ."Label tidak dikenal,,A,B,C,D,A,Tidak Diketahui\n";

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('questions', ['content' => 'English easy', 'category' => 'mudah']);
        $this->assertDatabaseHas('questions', ['content' => 'English hard', 'category' => 'sulit']);
        $this->assertDatabaseHas('questions', ['content' => 'Uppercase suspect', 'category' => 'sulit']);

        // An unrecognised label must not be stored verbatim, otherwise the module
        // filter and the coloured badge silently stop matching.
        $this->assertNull(Question::where('content', 'Label tidak dikenal')->firstOrFail()->category);
    }

    public function test_show_renders_a_coloured_badge_for_capitalised_category(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        // Pre-existing rows may still hold non-canonical values.
        $question = $this->makeQuestionWithOptions($module);
        $question->update(['category' => 'Mudah']);

        $this->admin()->get(route('admin.courses.modules.show', [$course, $module]))
            ->assertOk()
            ->assertSee('bg-green-100', false);
    }

    public function test_import_questions_strips_bom_and_normalizes_crlf(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $csv = "\xEF\xBB\xBFPertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Kunci\r\n"
            ."Judul soal,A,B,C,D,A\r\n"
            ."Soal kedua,A,B,C,D,A\r\n";

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertSessionHas('success', '2 soal berhasil diimpor ke modul.');

        $this->assertDatabaseHas('questions', ['content' => 'Judul soal']);
    }

    public function test_import_questions_falls_back_to_a_for_invalid_answer_key(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $csv = "Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Kunci\nKunci ngawur,Satu,Dua,Tiga,Empat,Z\n";

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertSessionHasNoErrors();

        $question = Question::where('content', 'Kunci ngawur')->firstOrFail();
        $this->assertSame('Satu', $question->options()->where('is_correct', true)->value('content'));
    }

    public function test_import_questions_skips_rows_without_question_text(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $csv = "Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Kunci\n"
            .",Kosong,A,B,C,D,A\n"
            ."   ,Spasi,A,B,C,D,A\n"
            ."Soal valid,A,B,C,D,A\n";

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertSessionHas('success', '1 soal berhasil diimpor ke modul.');

        $this->assertDatabaseCount('questions', 1);
        $this->assertDatabaseHas('questions', ['content' => 'Soal valid']);
    }

    public function test_import_questions_skips_rows_with_too_few_columns(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $csv = "Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Kunci\n"
            ."Baris pendek,A,B\n"
            ."Baris lengkap,A,B,C,D,B\n";

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertSessionHas('success', '1 soal berhasil diimpor ke modul.');

        $this->assertDatabaseHas('questions', ['content' => 'Baris lengkap']);
    }

    public function test_import_questions_only_creates_non_empty_options(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $csv = "Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Kunci\nHanya dua opsi,Ya,,,Tidak,B\n";

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertSessionHasNoErrors();

        $question = Question::where('content', 'Hanya dua opsi')->firstOrFail();
        $this->assertCount(2, $question->options);
    }

    public function test_import_questions_reports_empty_csv(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->createWithContent('kosong.csv', ''),
        ])->assertSessionHas('error', 'File CSV kosong atau format tidak valid.');

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_import_questions_validates_file_presence_and_type(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [])
            ->assertSessionHasErrors('csv_file');

        $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
            'csv_file' => UploadedFile::fake()->create('gambar.png', 4),
        ])->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_import_questions_extracts_images_from_zip(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $csv = "Pertanyaan,Gambar,Opsi A,Opsi B,Opsi C,Opsi D,Kunci,Kategori\n"
            ."Ada gambar,soal.png,A,B,C,D,A,mudah\n";

        try {
            $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
                'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
                'images_zip' => $this->makeZip(['img/soal.png' => $this->pngBytes(), 'catatan.txt' => 'diabaikan']),
            ])->assertSessionHas('success');

            // The importer unzips through storage_path(), not the Storage facade.
            $this->assertFileExists(storage_path('app/public/questions/soal.png'));
            $this->assertDatabaseHas('questions', ['image' => 'questions/soal.png']);
        } finally {
            $this->deleteStorageFiles('soal.png', 'catatan.txt');
        }
    }

    public function test_import_questions_rejects_non_image_entries_inside_zip(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $csv = "Pertanyaan,Gambar,Opsi A,Opsi B,Opsi C,Opsi D,Kunci,Kategori\n"
            ."Tanpa gambar,,A,B,C,D,A,mudah\n";

        try {
            $this->admin()->post(route('admin.courses.modules.import_questions', [$course, $module]), [
                'csv_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
                'images_zip' => $this->makeZip([
                    'evil.php' => '<?php echo 1;',
                    'nested/deep/gambar.JPG' => $this->jpegBytes(),
                    'palsu.jpg' => 'ini cuma teks biasa, bukan gambar',
                ]),
            ])->assertSessionHasNoErrors();

            // Whitelisted extension AND real image bytes are both required.
            $this->assertFileDoesNotExist(storage_path('app/public/questions/evil.php'));
            $this->assertFileExists(storage_path('app/public/questions/gambar.JPG'));
            // A .jpg that is really text must never be written out as an image.
            $this->assertFileDoesNotExist(storage_path('app/public/questions/palsu.jpg'));
        } finally {
            $this->deleteStorageFiles('evil.php', 'gambar.JPG', 'palsu.jpg');
        }
    }

    /* ------------------------------------------------------------------
     | DOCX template + import
     | ----------------------------------------------------------------- */

    public function test_download_docx_template_returns_a_readable_word_file(): void
    {
        $response = $this->admin()->get(route('admin.template_questions.docx'));

        $response->assertOk();
        $response->assertDownload('template_soal.docx');

        // Inspect the generated file directly: it is a real OOXML (zip) package on disk.
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);
        $this->assertSame('PK', substr(file_get_contents($path), 0, 2));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Template must be a valid docx package.');
        $document = $zip->getFromName('word/document.xml');
        $this->assertNotFalse($document);
        $zip->close();

        $this->assertStringContainsString('Kunci', $document);
        $this->assertStringContainsString('template_soal.docx', $path);
    }

    public function test_import_docx_parses_questions_options_keys_and_categories(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $docx = $this->makeDocx([
            '1. Siapa orang pertama?',
            'A. Soekarno',
            'B. Soeharto',
            'Kunci: A',
            'Kategori: Mudah',
            'Pembahasan: Pending pertama.',
            '2. Hasil 25 + 17?',
            'A. 32',
            'B. 42',
            'Jawaban: B',
            'Kategori: Susah',
        ]);

        try {
            $this->admin()->post(route('admin.courses.modules.import_questions.docx', [$course, $module]), [
                'docx_file' => $this->makeUpload($docx, 'soal.docx'),
            ])->assertSessionHas('success', '2 soal berhasil diimpor dari file .docx.');

            $this->assertDatabaseCount('questions', 2);

            $first = Question::where('content', 'Siapa orang pertama?')->firstOrFail();
            $this->assertSame('mudah', $first->category);
            $this->assertSame('Pending pertama.', $first->explanation);
            $this->assertSame('Soekarno', $first->options()->where('is_correct', true)->value('content'));

            $second = Question::where('content', 'Hasil 25 + 17?')->firstOrFail();
            $this->assertSame('sulit', $second->category);
            $this->assertSame('42', $second->options()->where('is_correct', true)->value('content'));
            $this->assertCount(2, $second->options);
        } finally {
            $this->deleteStorageFiles('docx_');
            @unlink($docx);
        }
    }

    public function test_import_docx_merges_inline_text_into_the_question(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $docx = $this->makeDocx([
            '1. Soal dua baris?',
            'Lanjutan baris kedua.',
            'A. benar',
            'B. salah',
            'Kunci: A',
        ]);

        try {
            $this->admin()->post(route('admin.courses.modules.import_questions.docx', [$course, $module]), [
                'docx_file' => $this->makeUpload($docx, 'soal.docx'),
            ])->assertSessionHas('success', '1 soal berhasil diimpor dari file .docx.');

            $question = Question::firstOrFail();
            $this->assertStringContainsString('Soal dua baris?', $question->content);
            $this->assertStringContainsString('Lanjutan baris kedua.', $question->content);
        } finally {
            $this->deleteStorageFiles('docx_');
            @unlink($docx);
        }
    }

    public function test_import_docx_reports_when_no_question_can_be_detected(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();
        $docx = $this->makeDocx(['Paragraf biasa tanpa nomor.', 'Lain lagi.']);

        try {
            $this->admin()->post(route('admin.courses.modules.import_questions.docx', [$course, $module]), [
                'docx_file' => $this->makeUpload($docx, 'sampul.docx'),
            ])->assertSessionHas('error', 'Tidak ditemukan soal dalam format yang benar. Gunakan template yang disediakan.');

            $this->assertDatabaseCount('questions', 0);
        } finally {
            @unlink($docx);
        }
    }

    public function test_import_docx_validates_upload(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $this->admin()->post(route('admin.courses.modules.import_questions.docx', [$course, $module]), [])
            ->assertSessionHasErrors('docx_file');

        $this->admin()->post(route('admin.courses.modules.import_questions.docx', [$course, $module]), [
            'docx_file' => UploadedFile::fake()->create('bukan.pdf', 4),
        ])->assertSessionHasErrors('docx_file');
    }

    /* ------------------------------------------------------------------
     | AI generation
     | ----------------------------------------------------------------- */

    public function test_generate_question_returns_400_when_groq_is_not_configured(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $this->mock(QuestionGeneratorService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnFalse();
        });

        $this->admin()->postJson(route('admin.courses.modules.questions.generate', [$course, $module]), [
            'topic' => 'Persamaan Kuadrat',
            'difficulty' => 'sedang',
        ])->assertStatus(400)->assertJson([
            'success' => false,
            'message' => 'GROQ_API_KEY belum dikonfigurasi. Tambahkan di file .env',
        ]);
    }

    public function test_generate_question_validates_input(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $this->mock(QuestionGeneratorService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
        });

        $this->admin()->postJson(route('admin.courses.modules.questions.generate', [$course, $module]), [
            'topic' => '',
            'difficulty' => 'ekstrem',
            'count' => 99,
        ])->assertStatus(422)->assertJsonValidationErrors(['topic', 'difficulty', 'count']);
    }

    public function test_generate_question_returns_generated_questions(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $generated = [
            ['question' => 'Hasil 1+1?', 'options' => ['1', '2'], 'correct' => '1'],
        ];

        $this->mock(QuestionGeneratorService::class, function ($mock) use ($generated) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('generate')->once()->andReturn($generated);
            $mock->shouldReceive('getLastError')->andReturnNull();
        });

        $this->admin()->postJson(route('admin.courses.modules.questions.generate', [$course, $module]), [
            'topic' => 'Persamaan Kuadrat',
            'difficulty' => 'sedang',
            'count' => 1,
        ])->assertOk()->assertJson(['success' => true, 'questions' => $generated]);
    }

    public function test_generate_question_maps_generator_errors_to_messages(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $service = Mockery::mock(QuestionGeneratorService::class);
        $service->shouldReceive('isConfigured')->andReturnTrue();
        $service->shouldReceive('generate')->once()->andReturnNull();
        $service->lastError = 'rate_limit';

        $this->app->instance(QuestionGeneratorService::class, $service);

        $this->admin()->postJson(route('admin.courses.modules.questions.generate', [$course, $module]), [
            'topic' => 'Persamaan Kuadrat',
            'difficulty' => 'sedang',
        ])->assertStatus(500)->assertJson([
            'success' => false,
            'message' => 'Batas harian Groq API telah tercapai. Silakan coba lagi besok, atau upgrade akun Groq Anda.',
        ]);
    }

    public function test_generate_question_maps_unknown_error_to_fallback_message(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create();

        $service = Mockery::mock(QuestionGeneratorService::class);
        $service->shouldReceive('isConfigured')->andReturnTrue();
        $service->shouldReceive('generate')->once()->andReturnNull();
        $service->lastError = null;

        $this->app->instance(QuestionGeneratorService::class, $service);

        $this->admin()->postJson(route('admin.courses.modules.questions.generate', [$course, $module]), [
            'topic' => 'Persamaan Kuadrat',
            'difficulty' => 'sedang',
        ])->assertStatus(500)->assertJson([
            'success' => false,
            'message' => 'Gagal menghasilkan soal. Coba lagi dengan topik yang berbeda.',
        ]);
    }

    public function test_save_generated_question_persists_question_with_options(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $this->admin()->postJson(route('admin.courses.modules.questions.save_generated', [$course, $module]), [
            'content' => 'Soal dari AI',
            'options' => [
                ['content' => 'Pilihan salah', 'is_correct' => false],
                ['content' => 'Pilihan benar', 'is_correct' => true],
                ['content' => 'Pilihan lain', 'is_correct' => false],
                ['content' => 'Pilihan keempat', 'is_correct' => false],
            ],
            'category' => 'sulit',
            'explanation' => 'Penjelasan AI',
        ])->assertOk()->assertJson(['success' => true]);

        $question = Question::where('content', 'Soal dari AI')->firstOrFail();
        $this->assertSame($module->id, $question->module_id);
        $this->assertSame('sulit', $question->category);
        $this->assertCount(4, $question->options);
        $this->assertSame('Pilihan benar', $question->options()->where('is_correct', true)->value('content'));
    }

    public function test_save_generated_question_requires_exactly_four_options(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create();

        $this->admin()->postJson(route('admin.courses.modules.questions.save_generated', [$course, $module]), [
            'content' => 'Soal kurang opsi',
            'options' => [
                ['content' => 'A', 'is_correct' => true],
                ['content' => 'B', 'is_correct' => false],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('options');

        $this->assertDatabaseCount('questions', 0);
    }

    /* ------------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------- */

    private function uniqueFileName(string $suffix): string
    {
        return uniqid('test_', true).'.'.preg_replace('/[^A-Za-z0-9]/', '', $suffix);
    }

    /**
     * A genuinely decodable 1x1 PNG, so archive-import fixtures carry real image
     * bytes rather than text that only looks like an image by extension.
     */
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk'
            .'YPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );
    }

    private function jpegBytes(): string
    {
        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function deleteStorageFiles(string ...$needles): void
    {
        $dir = storage_path('app/public/questions');
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir.'/*') ?: [] as $file) {
            foreach ($needles as $needle) {
                if (str_contains(basename($file), $needle)) {
                    @unlink($file);
                    break;
                }
            }
        }
    }

    private function makeZip(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip').'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return new UploadedFile($path, 'images.zip', 'application/zip', null, true);
    }

    private function makeDocx(array $paragraphs): string
    {
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $escaped = htmlspecialchars($paragraph, ENT_XML1);
            $body .= '<w:p><w:r><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>';
        }

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$body.'</w:body></w:document>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';

        $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';

        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('word/_rels/document.xml.rels', $docRels);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        return $path;
    }

    private function makeUpload(string $path, string $name): UploadedFile
    {
        return new UploadedFile($path, $name, null, null, true);
    }
}
