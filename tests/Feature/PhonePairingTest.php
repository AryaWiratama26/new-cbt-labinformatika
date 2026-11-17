<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

/**
 * Regresi bug "ujian terjeda sendiri": pairing kamera HP tidak boleh hilang
 * di tengah ujian selama HP masih mengirim heartbeat.
 */
class PhonePairingTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classroom = Classroom::factory()->create();
    }

    private function phoneExam(): Exam
    {
        [, $module] = $this->makeCourseWithModule(1);
        $exam = $this->makeExamWithSchedule($module, $this->classroom);
        $exam->update(['camera_proctoring' => true, 'camera_phone' => true]);

        return $exam->fresh();
    }

    /** Token baru yang sudah di-scan HP (verify). */
    private function pairPhone(User $student, Exam $exam): string
    {
        $token = $this->actingAs($student)
            ->postJson(route('proctor.pair_token', $exam))
            ->assertOk()
            ->json('token');

        $this->postJson(route('proctor.pair.verify', $token))
            ->assertOk()
            ->assertJson(['valid' => true]);

        return $token;
    }

    private function startExam(User $student, Exam $exam): void
    {
        $this->actingAs($student)->post(route('proctor.camera_ready', $exam))->assertOk();
        $this->actingAs($student)
            ->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.attempt', $exam));
    }

    private function pairStatus(User $student, Exam $exam): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($student)->getJson(route('proctor.pair_status', $exam));
    }

    public function test_pairing_survives_past_five_minutes_while_phone_sends_heartbeats(): void
    {
        $exam = $this->phoneExam();
        $student = $this->makeStudent($this->classroom);
        $token = $this->pairPhone($student, $exam);
        $this->startExam($student, $exam);

        for ($i = 0; $i < 8; $i++) {
            $this->travel(1)->minutes();
            $this->postJson(route('proctor.pair.event', $token), ['type' => 'heartbeat'])->assertOk();
        }

        $this->pairStatus($student, $exam)
            ->assertOk()
            ->assertJson(['paired' => true, 'camera_active' => true, 'stale' => false]);
    }

    public function test_phone_that_stops_sending_events_is_reported_stale_then_recovers(): void
    {
        $exam = $this->phoneExam();
        $student = $this->makeStudent($this->classroom);
        $token = $this->pairPhone($student, $exam);
        $this->startExam($student, $exam);

        $this->travel(3)->minutes();

        // Layar HP terkunci: tidak ada event, tapi pairing TIDAK hilang.
        $this->pairStatus($student, $exam)->assertJson(['paired' => true, 'stale' => true]);

        // HP dibuka lagi: heartbeat berikutnya langsung memulihkan.
        $this->postJson(route('proctor.pair.event', $token), ['type' => 'heartbeat'])->assertOk();

        $this->pairStatus($student, $exam)->assertJson(['paired' => true, 'stale' => false]);
    }

    public function test_explicit_camera_off_is_reported_and_camera_on_recovers(): void
    {
        $exam = $this->phoneExam();
        $student = $this->makeStudent($this->classroom);
        $token = $this->pairPhone($student, $exam);
        $this->startExam($student, $exam);

        $this->postJson(route('proctor.pair.event', $token), ['type' => 'camera_off'])->assertOk();
        $this->pairStatus($student, $exam)->assertJson(['camera_active' => false]);

        $this->postJson(route('proctor.pair.event', $token), ['type' => 'camera_on'])->assertOk();
        $this->pairStatus($student, $exam)->assertJson(['camera_active' => true]);
    }

    public function test_rescanning_during_a_running_exam_binds_the_new_pairing_to_the_session(): void
    {
        $exam = $this->phoneExam();
        $student = $this->makeStudent($this->classroom);
        $this->pairPhone($student, $exam);
        $this->startExam($student, $exam);

        $session = ExamSession::where('user_id', $student->id)->where('exam_id', $exam->id)->firstOrFail();

        // Mahasiswa scan ulang di tengah ujian lalu menekan "Lanjutkan".
        $newToken = $this->pairPhone($student, $exam);
        $this->actingAs($student)
            ->post(route('student.exams.start', $exam))
            ->assertRedirect(route('student.exams.attempt', $exam));

        $this->postJson(route('proctor.pair.event', $newToken), ['type' => 'heartbeat'])->assertOk();

        $this->assertDatabaseHas('proctor_events', [
            'exam_session_id' => $session->id,
            'source' => 'phone',
            'type' => 'heartbeat',
        ]);
        $this->assertNotNull($session->fresh()->phone_seen_at);

        $this->travel(8)->minutes();
        $this->postJson(route('proctor.pair.event', $newToken), ['type' => 'heartbeat'])->assertOk();
        $this->pairStatus($student, $exam)->assertJson(['paired' => true, 'stale' => false]);
    }

    public function test_events_are_rejected_before_the_exam_starts(): void
    {
        $exam = $this->phoneExam();
        $student = $this->makeStudent($this->classroom);
        $token = $this->pairPhone($student, $exam);

        $this->postJson(route('proctor.pair.event', $token), ['type' => 'heartbeat'])
            ->assertStatus(409);
    }

    public function test_events_are_rejected_after_the_session_finishes(): void
    {
        $exam = $this->phoneExam();
        $student = $this->makeStudent($this->classroom);
        $token = $this->pairPhone($student, $exam);
        $this->startExam($student, $exam);

        ExamSession::where('user_id', $student->id)->where('exam_id', $exam->id)
            ->update(['finished_at' => now()]);

        $this->postJson(route('proctor.pair.event', $token), ['type' => 'heartbeat'])
            ->assertForbidden();
    }

    public function test_old_phone_page_does_not_overwrite_a_newer_pairing_pointer(): void
    {
        $exam = $this->phoneExam();
        $student = $this->makeStudent($this->classroom);
        $oldToken = $this->pairPhone($student, $exam);
        $this->startExam($student, $exam);

        $newToken = $this->pairPhone($student, $exam);

        // Halaman HP lama masih mengirim heartbeat.
        $this->postJson(route('proctor.pair.event', $oldToken), ['type' => 'heartbeat'])->assertOk();

        $this->pairStatus($student, $exam)->assertJson(['token' => $newToken]);
    }
}
