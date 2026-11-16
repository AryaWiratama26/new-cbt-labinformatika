<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class ExamArchiveTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private function activeExamWithStudent(): array
    {
        $classroom = \App\Models\Classroom::factory()->create();
        [, $module] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $classroom);
        $student = $this->makeStudent($classroom);

        return [$exam->fresh(), $student, $classroom];
    }

    public function test_archived_exam_hidden_from_index_by_default(): void
    {
        [$exam] = $this->activeExamWithStudent();
        $admin = $this->makeAdmin();

        $exam->update(['is_archived' => true, 'is_active' => false]);

        $this->actingAs($admin)
            ->get(route('admin.exams.index'))
            ->assertOk()
            ->assertDontSee($exam->title, false);

        $this->actingAs($admin)
            ->get(route('admin.exams.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee($exam->title, false);
    }

    public function test_archive_hides_exam_and_deactivates_it(): void
    {
        [$exam] = $this->activeExamWithStudent();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.exams.archive', $exam))
            ->assertRedirect(route('admin.exams.index'));

        $exam->refresh();
        $this->assertTrue($exam->is_archived);
        $this->assertFalse($exam->is_active);
    }

    public function test_cannot_archive_exam_with_running_session(): void
    {
        [$exam, $student] = $this->activeExamWithStudent();
        $admin = $this->makeAdmin();

        ExamSession::factory()->create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'finished_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.exams.archive', $exam))
            ->assertRedirect(route('admin.exams.index'))
            ->assertSessionHas('error');

        $this->assertFalse($exam->fresh()->is_archived);
    }

    public function test_abandoned_session_does_not_block_archive_once_schedule_ended(): void
    {
        [$exam, $student] = $this->activeExamWithStudent();
        $admin = $this->makeAdmin();

        // Abandoned session: started but never submitted.
        ExamSession::factory()->create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'finished_at' => null,
        ]);

        // Schedule fully in the past (list shows "Selesai").
        $exam->classrooms()->updateExistingPivot(
            $exam->classrooms->first()->id,
            ['start_time' => now()->subDays(2), 'end_time' => now()->subDay()]
        );

        $this->actingAs($admin)
            ->post(route('admin.exams.archive', $exam))
            ->assertRedirect(route('admin.exams.index'))
            ->assertSessionHas('success');

        $this->assertTrue($exam->fresh()->is_archived);
    }

    public function test_finished_sessions_do_not_block_archive_and_results_stay_accessible(): void
    {
        [$exam, $student] = $this->activeExamWithStudent();
        $admin = $this->makeAdmin();

        ExamSession::factory()->create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'finished_at' => now(),
            'score' => 80,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.exams.archive', $exam))
            ->assertRedirect(route('admin.exams.index'));

        $this->assertTrue($exam->fresh()->is_archived);

        $this->actingAs($admin)
            ->get(route('admin.exams.results', $exam))
            ->assertOk();
    }

    public function test_unarchive_keeps_exam_inactive_until_manually_enabled(): void
    {
        [$exam] = $this->activeExamWithStudent();
        $admin = $this->makeAdmin();
        $exam->update(['is_archived' => true, 'is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admin.exams.unarchive', $exam))
            ->assertRedirect(route('admin.exams.index', ['status' => 'archived']));

        $exam->refresh();
        $this->assertFalse($exam->is_archived);
        $this->assertFalse($exam->is_active);
    }

    public function test_archived_exam_hidden_from_student_dashboard(): void
    {
        [$exam, $student] = $this->activeExamWithStudent();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($exam->title, false);

        $exam->update(['is_archived' => true, 'is_active' => false]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee($exam->title, false);
    }
}
