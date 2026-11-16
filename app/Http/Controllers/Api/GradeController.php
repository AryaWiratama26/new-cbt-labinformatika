<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    private function gradeStatus(?float $score, int $passingGrade): string
    {
        if ($score === null) {
            return 'belum_dinilai';
        }

        return $score >= $passingGrade ? 'lulus' : 'gagal';
    }

    private function examPayload(Exam $exam): array
    {
        $exam->loadMissing('course', 'classrooms');

        return [
            'id' => $exam->id,
            'title' => $exam->title,
            'course' => $exam->course ? ['id' => $exam->course->id, 'name' => $exam->course->name] : null,
            'passing_grade' => (int) $exam->passing_grade,
            'max_attempts' => (int) $exam->max_attempts,
            'is_active' => (bool) $exam->is_active,
            'is_archived' => (bool) $exam->is_archived,
            'schedules' => $exam->classrooms->map(fn ($c) => [
                'classroom_id' => $c->id,
                'classroom_name' => $c->name,
                'start_time' => $c->pivot->start_time,
                'end_time' => $c->pivot->end_time,
                'duration_minutes' => (int) $c->pivot->duration_minutes,
                'is_active' => (bool) $c->pivot->is_active,
            ])->values(),
        ];
    }

    private function sessionPayload(ExamSession $session, Exam $exam): array
    {
        $score = $session->score !== null ? (float) $session->score : null;

        return [
            'nim' => $session->user->username,
            'nama' => $session->user->name,
            'kelas' => $session->user->classroom?->name,
            'exam_id' => $exam->id,
            'attempt_number' => (int) $session->attempt_number,
            'score' => $score,
            'status' => $this->gradeStatus($score, (int) $exam->passing_grade),
            'is_remedial_attempt' => (int) $session->attempt_number > 1,
            'finished_at' => $session->finished_at?->toIso8601String(),
        ];
    }

    public function courses()
    {
        $data = Course::orderBy('name')->get(['id', 'code', 'name']);

        return response()->json(['data' => $data]);
    }

    public function classrooms()
    {
        $data = Classroom::orderBy('name')->get(['id', 'name', 'academic_year', 'semester']);

        return response()->json(['data' => $data]);
    }

    public function exams(Request $request)
    {
        $query = Exam::with('course', 'classrooms')->latest();

        if ($request->query('course_id')) {
            $query->where('course_id', $request->query('course_id'));
        }

        if ($request->query('classroom_id')) {
            $query->whereHas('classrooms', fn ($q) => $q->where('classroom_id', $request->query('classroom_id')));
        }

        $archived = $request->query('archived', '0');
        if ($archived === 'all') {
            // no filter
        } elseif ($archived === '1' || $archived === 'true') {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', false);
        }

        $exams = $query->paginate(50);

        return response()->json([
            'data' => $exams->getCollection()->map(fn ($e) => $this->examPayload($e))->values(),
            'meta' => [
                'current_page' => $exams->currentPage(),
                'per_page' => $exams->perPage(),
                'total' => $exams->total(),
                'last_page' => $exams->lastPage(),
            ],
        ]);
    }

    public function examGrades(Request $request, int $exam)
    {
        $examModel = Exam::find($exam);
        if (! $examModel) {
            return response()->json(['message' => 'Ujian tidak ditemukan.'], 404);
        }

        $query = ExamSession::where('exam_id', $examModel->id)
            ->whereNotNull('finished_at')
            ->with(['user.classroom'])
            ->orderBy('user_id')
            ->orderBy('attempt_number');

        if ($request->query('classroom_id')) {
            $query->whereHas('user', fn ($q) => $q->where('classroom_id', $request->query('classroom_id')));
        }

        if (in_array($request->query('status'), ['lulus', 'gagal'], true)) {
            $passing = (int) $examModel->passing_grade;
            $query->when(
                $request->query('status') === 'lulus',
                fn ($q) => $q->where('score', '>=', $passing),
                fn ($q) => $q->where('score', '<', $passing),
            );
        }

        $sessions = $query->paginate(50);

        return response()->json([
            'data' => [
                'exam' => $this->examPayload($examModel),
                'grades' => $sessions->getCollection()->map(fn ($s) => $this->sessionPayload($s, $examModel))->values(),
            ],
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
            ],
        ]);
    }

    public function classroomRecap(int $classroom)
    {
        $classroomModel = Classroom::find($classroom);
        if (! $classroomModel) {
            return response()->json(['message' => 'Kelas tidak ditemukan.'], 404);
        }

        $exams = Exam::whereHas('classrooms', fn ($q) => $q->where('classroom_id', $classroomModel->id))
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with('course')
            ->get()
            ->sortBy(fn ($exam) => $exam->getScheduleForClassroom($classroomModel->id)?->start_time)
            ->values();

        $students = User::where('role', 'mahasiswa')
            ->where('classroom_id', $classroomModel->id)
            ->orderBy('name')
            ->get();

        // Latest finished attempt per student+exam.
        $latest = ExamSession::whereIn('exam_id', $exams->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))
            ->whereNotNull('finished_at')
            ->orderByDesc('attempt_number')
            ->get()
            ->unique(fn ($s) => $s->user_id.'_'.$s->exam_id);

        $rows = $students->map(function ($student) use ($exams, $latest) {
            $grades = [];
            foreach ($exams as $exam) {
                $session = $latest->first(fn ($s) => $s->user_id === $student->id && $s->exam_id === $exam->id);
                $score = $session && $session->score !== null ? (float) $session->score : null;
                $grades[] = [
                    'exam_id' => $exam->id,
                    'exam_title' => $exam->title,
                    'attempt_number' => $session ? (int) $session->attempt_number : null,
                    'score' => $score,
                    'status' => $session ? $this->gradeStatus($score, (int) $exam->passing_grade) : 'belum_mengerjakan',
                    'finished_at' => $session?->finished_at?->toIso8601String(),
                ];
            }
            $scored = array_filter(array_column($grades, 'score'), fn ($v) => $v !== null);

            return [
                'nim' => $student->username,
                'nama' => $student->name,
                'grades' => $grades,
                'average' => $scored ? round(array_sum($scored) / count($scored), 2) : null,
            ];
        });

        return response()->json([
            'data' => [
                'classroom' => $classroomModel->only(['id', 'name', 'academic_year', 'semester']),
                'exams' => $exams->map(fn ($e) => ['id' => $e->id, 'title' => $e->title])->values(),
                'rows' => $rows->values(),
            ],
        ]);
    }

    public function studentGrades(string $nim)
    {
        $student = User::where('username', $nim)->where('role', 'mahasiswa')->first();
        if (! $student) {
            return response()->json(['message' => 'Mahasiswa tidak ditemukan.'], 404);
        }

        $sessions = ExamSession::where('user_id', $student->id)
            ->whereNotNull('finished_at')
            ->with('exam')
            ->orderByDesc('finished_at')
            ->paginate(50);

        return response()->json([
            'data' => [
                'nim' => $student->username,
                'nama' => $student->name,
                'kelas' => $student->classroom?->name,
                'grades' => $sessions->getCollection()->map(function ($s) {
                    $score = $s->score !== null ? (float) $s->score : null;

                    return [
                        'exam_id' => $s->exam_id,
                        'exam_title' => $s->exam?->title,
                        'attempt_number' => (int) $s->attempt_number,
                        'score' => $score,
                        'status' => $s->exam ? $this->gradeStatus($score, (int) $s->exam->passing_grade) : 'belum_dinilai',
                        'is_remedial_attempt' => (int) $s->attempt_number > 1,
                        'finished_at' => $s->finished_at?->toIso8601String(),
                    ];
                })->values(),
            ],
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
            ],
        ]);
    }
}
