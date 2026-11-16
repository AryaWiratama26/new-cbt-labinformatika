<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithExamFixtures;
use Tests\TestCase;

class ApiGradesTest extends TestCase
{
    use InteractsWithExamFixtures;
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->key] = ApiKey::generate('Portal Test');
    }

    private function authHeaders(string $key = null): array
    {
        return [
            'X-API-Key' => $key ?? $this->key,
            'Accept' => 'application/json',
        ];
    }

    private function seedGrades(): array
    {
        $classroom = \App\Models\Classroom::factory()->create();
        [, $module] = $this->makeCourseWithModule(2);
        $exam = $this->makeExamWithSchedule($module, $classroom);
        $student = $this->makeStudent($classroom);

        ExamSession::factory()->create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'attempt_number' => 1,
            'finished_at' => now(),
            'score' => 80,
        ]);

        return [$exam->fresh(), $student, $classroom];
    }

    public function test_rejects_missing_and_invalid_keys(): void
    {
        $this->getJson('/api/v1/exams')->assertUnauthorized();
        $this->getJson('/api/v1/exams', ['X-API-Key' => 'cbt_live_nope'])->assertUnauthorized();
    }

    public function test_rejects_expired_key(): void
    {
        [$record, $plain] = ApiKey::generate('Expired');
        $record->update(['expires_at' => now()->subDay()]);

        $this->getJson('/api/v1/exams', $this->authHeaders($plain))->assertForbidden();
    }

    public function test_revoked_key_forbidden(): void
    {
        [$record, $plain] = ApiKey::generate('To Revoke');
        $record->update(['revoked_at' => now()]);

        $this->getJson('/api/v1/exams', $this->authHeaders($plain))->assertForbidden();
    }

    public function test_lists_reference_data(): void
    {
        [$exam] = $this->seedGrades();

        $this->getJson('/api/v1/courses', $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['id' => $exam->course_id]);

        $this->getJson('/api/v1/classrooms', $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['name' => $exam->classrooms->first()->name]);
    }

    public function test_exams_list_hides_archived_by_default(): void
    {
        [$exam] = $this->seedGrades();
        $exam->update(['is_archived' => true, 'is_active' => false]);

        $this->getJson('/api/v1/exams', $this->authHeaders())
            ->assertOk()
            ->assertJsonMissing(['id' => $exam->id], false)
            ->assertJsonFragment(['total' => 0]);

        $this->getJson('/api/v1/exams?archived=1', $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['id' => $exam->id, 'is_archived' => true]);

        $this->getJson('/api/v1/exams?archived=all', $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['id' => $exam->id]);
    }

    public function test_exam_grades_payload_and_status(): void
    {
        [$exam, $student] = $this->seedGrades();

        $res = $this->getJson("/api/v1/exams/{$exam->id}/grades", $this->authHeaders())->assertOk();

        $res->assertJsonFragment([
            'nim' => $student->username,
            'nama' => $student->name,
            'score' => 80.0,
            'status' => 'lulus',
            'attempt_number' => 1,
            'is_remedial_attempt' => false,
        ]);

        $this->getJson("/api/v1/exams/{$exam->id}/grades?status=gagal", $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['total' => 0]);

        $this->getJson('/api/v1/exams/999999/grades', $this->authHeaders())->assertNotFound();
    }

    public function test_classroom_recap_and_student_grades(): void
    {
        [$exam, $student, $classroom] = $this->seedGrades();

        $this->getJson("/api/v1/classrooms/{$classroom->id}/recap", $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['nim' => $student->username, 'score' => 80.0, 'average' => 80.0]);

        $this->getJson('/api/v1/classrooms/999999/recap', $this->authHeaders())->assertNotFound();

        $this->getJson("/api/v1/students/{$student->username}/grades", $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['nim' => $student->username, 'exam_title' => $exam->title, 'status' => 'lulus']);

        $this->getJson('/api/v1/students/TIDAKADA/grades', $this->authHeaders())->assertNotFound();
    }
}
