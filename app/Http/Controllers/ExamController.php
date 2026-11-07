<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Module;
use App\Models\User;
use App\Services\AiCodeGrader;
use App\Services\CodeRunnerService;
use App\Services\ExamScoringService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    public function __construct(private ExamScoringService $scoring) {}

    /**
     * Validasi bisnis jadwal per kelas: end > start, tanpa duplikat kelas,
     * dan durasi tidak melebihi window jadwal.
     *
     * @throws ValidationException
     */
    private function validateScheduleRows(array $rows): void
    {
        $seen = [];

        foreach (array_values($rows) as $i => $row) {
            $label = 'Jadwal #'.($i + 1);
            $start = Carbon::parse($row['start_time']);
            $end = Carbon::parse($row['end_time']);

            if ($end->lte($start)) {
                throw ValidationException::withMessages([
                    "classrooms.{$i}.end_time" => "$label: waktu selesai harus setelah waktu mulai.",
                ]);
            }

            if (in_array($row['classroom_id'], $seen, true)) {
                throw ValidationException::withMessages([
                    "classrooms.{$i}.classroom_id" => "$label: kelas duplikat.",
                ]);
            }
            $seen[] = $row['classroom_id'];

            $window = $start->diffInMinutes($end);
            if ((int) $row['duration_minutes'] > $window) {
                throw ValidationException::withMessages([
                    "classrooms.{$i}.duration_minutes" => "$label: durasi melebihi window jadwal ({$window} menit).",
                ]);
            }
        }
    }

    public function index()
    {
        $courses = Course::orderBy('name')->get();
        $classrooms = Classroom::orderBy('name')->get();
        $group = request('group');

        $query = Exam::with(['course', 'classrooms']);

        $search = request('search');
        $courseId = request('course_id');
        $classroomId = request('classroom_id');
        $status = request('status');

        $query->when($search, fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->when($classroomId, fn ($q) => $q->whereHas('classrooms', fn ($cq) => $cq->where('classroom_id', $classroomId)))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true)->whereHas('classrooms', fn ($cq) => $cq->where('end_time', '>', now())))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($status === 'upcoming', fn ($q) => $q->whereHas('classrooms', fn ($cq) => $cq->where('start_time', '>', now())))
            ->when($status === 'finished', fn ($q) => $q->whereHas('classrooms', fn ($cq) => $cq->where('end_time', '<=', now())));

        if ($group === 'course') {
            $exams = $query->latest()->get()->groupBy(fn ($e) => $e->course->name ?? 'Tanpa Matkul');

            return view('admin.exams.index', compact('exams', 'courses', 'classrooms', 'group'));
        }

        $exams = $query->latest()->paginate(20)->appends(request()->except('page'));

        return view('admin.exams.index', compact('exams', 'courses', 'classrooms', 'group'));
    }

    public function create()
    {
        $modules = Module::with('course')->withCount('questions')->orderBy('course_id')->get();
        $classrooms = Classroom::orderBy('name')->get();

        return view('admin.exams.create', compact('modules', 'classrooms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module_id' => 'required|exists:modules,id',
            'classrooms' => 'required|array|min:1',
            'classrooms.*.classroom_id' => 'required|exists:classrooms,id',
            'classrooms.*.start_time' => 'required|date',
            'classrooms.*.end_time' => 'required|date',
            'classrooms.*.duration_minutes' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'passing_grade' => 'required|integer|min:0|max:100',
            'max_attempts' => 'required|integer|min:1|max:10',
            'max_tab_switches' => 'nullable|integer|min:1|max:99',
            'require_fullscreen' => 'boolean',
            'camera_proctoring' => 'boolean',
            'camera_phone' => 'boolean',
            'classrooms.*.pin' => 'nullable|string|max:10',
            'classrooms.*.is_active' => 'boolean',
        ]);

        $module = Module::findOrFail($validated['module_id']);

        $this->validateScheduleRows($validated['classrooms']);

        $exam = Exam::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'course_id' => $module->course_id,
            'module_id' => $validated['module_id'],
            'is_active' => $request->boolean('is_active'),
            'passing_grade' => $validated['passing_grade'],
            'max_attempts' => $validated['max_attempts'],
            'max_tab_switches' => $validated['max_tab_switches'] ?? null,
            'require_fullscreen' => $request->boolean('require_fullscreen'),
            'camera_proctoring' => $request->boolean('camera_proctoring'),
            'camera_phone' => $request->boolean('camera_phone'),
        ]);

        $classroomsData = [];
        foreach ($validated['classrooms'] as $item) {
            $classroomsData[$item['classroom_id']] = [
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time'],
                'duration_minutes' => $item['duration_minutes'],
                'pin' => $item['pin'] ?: null,
                'is_active' => $item['is_active'] ?? true,
            ];
        }
        $exam->classrooms()->sync($classroomsData);

        Cache::forget('admin_dashboard_stats');

        return redirect()->route('admin.exams.index')->with('success', 'Jadwal ujian berhasil dibuat.');
    }

    public function edit(Exam $exam)
    {
        $modules = Module::with('course')->withCount('questions')->orderBy('course_id')->get();
        $classrooms = Classroom::orderBy('name')->get();
        $exam->load('classrooms');

        return view('admin.exams.edit', compact('exam', 'modules', 'classrooms'));
    }

    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module_id' => 'required|exists:modules,id',
            'classrooms' => 'required|array|min:1',
            'classrooms.*.classroom_id' => 'required|exists:classrooms,id',
            'classrooms.*.start_time' => 'required|date',
            'classrooms.*.end_time' => 'required|date',
            'classrooms.*.duration_minutes' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'passing_grade' => 'required|integer|min:0|max:100',
            'max_attempts' => 'required|integer|min:1|max:10',
            'max_tab_switches' => 'nullable|integer|min:1|max:99',
            'require_fullscreen' => 'boolean',
            'camera_proctoring' => 'boolean',
            'camera_phone' => 'boolean',
            'classrooms.*.pin' => 'nullable|string|max:10',
            'classrooms.*.is_active' => 'boolean',
        ]);

        $module = Module::findOrFail($validated['module_id']);

        $this->validateScheduleRows($validated['classrooms']);

        $detached = $exam->classrooms()->pluck('classrooms.id')->diff(collect($validated['classrooms'])->pluck('classroom_id'));
        if ($detached->isNotEmpty() && ExamSession::where('exam_id', $exam->id)->whereNull('finished_at')->exists()) {
            throw ValidationException::withMessages(['classrooms' => 'Tidak dapat melepas kelas yang masih memiliki sesi ujian berjalan.']);
        }

        $exam->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'course_id' => $module->course_id,
            'module_id' => $validated['module_id'],
            'is_active' => $request->boolean('is_active'),
            'passing_grade' => $validated['passing_grade'],
            'max_attempts' => $validated['max_attempts'],
            'max_tab_switches' => $validated['max_tab_switches'] ?? null,
            'require_fullscreen' => $request->boolean('require_fullscreen'),
            'camera_proctoring' => $request->boolean('camera_proctoring'),
            'camera_phone' => $request->boolean('camera_phone'),
        ]);

        $classroomsData = [];
        foreach ($validated['classrooms'] as $item) {
            $classroomsData[$item['classroom_id']] = [
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time'],
                'duration_minutes' => $item['duration_minutes'],
                'pin' => $item['pin'] ?: null,
                'is_active' => $item['is_active'] ?? true,
            ];
        }
        $exam->classrooms()->sync($classroomsData);

        Cache::forget('admin_dashboard_stats');

        return redirect()->route('admin.exams.index')->with('success', 'Jadwal ujian berhasil diperbarui.');
    }

    public function destroy(Exam $exam)
    {
        if (ExamSession::where('exam_id', $exam->id)->exists()) {
            return redirect()->route('admin.exams.index')->with('error', 'Ujian tidak dapat dihapus karena sudah memiliki sesi/nilai siswa.');
        }

        $exam->delete();
        Cache::forget('admin_dashboard_stats');

        return redirect()->route('admin.exams.index')->with('success', 'Ujian berhasil dihapus.');
    }

    public function show(Exam $exam)
    {
        $exam->load('course', 'classrooms', 'module');
        $questions = $exam->getQuestions();

        return view('admin.exams.show', compact('exam', 'questions'));
    }

    public function results(Exam $exam)
    {
        $exam->load('course', 'classrooms');

        $allSessions = ExamSession::where('exam_id', $exam->id)
            ->with('user', 'answers')
            ->orderBy('user_id')
            ->orderBy('attempt_number')
            ->get();

        $codingIds = $exam->getQuestions()->filter(fn ($q) => $q->isCoding())->pluck('id');

        $allSessions->each(function ($session) use ($codingIds) {
            $session->has_pending_coding = $session->answers->contains(
                fn ($answer) => $codingIds->contains($answer->question_id) && $answer->isUnscoredCodingAnswer()
            );
        });

        $students = $allSessions->groupBy('user_id');

        return view('admin.exams.results', compact('exam', 'students'));
    }

    public function monitor(Request $request, Exam $exam)
    {
        $exam->load('course', 'classrooms');

        $classroomIds = $exam->classrooms()->wherePivot('is_active', true)->pluck('classroom_id');

        $students = User::where('role', 'mahasiswa')
            ->whereIn('classroom_id', $classroomIds)
            ->orderBy('name')
            ->get();

        $sessions = ExamSession::where('exam_id', $exam->id)
            ->whereIn('user_id', $students->pluck('id'))
            ->with('proctorEvents')
            ->orderByDesc('attempt_number')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($s) => $s->first());

        $participants = $students->map(function ($student) use ($sessions) {
            $session = $sessions->get($student->id);

            if (! $session) {
                $student->status = 'not_started';
                $student->started_at = null;
                $student->finished_at = null;
                $student->score = null;
            } elseif ($session->finished_at) {
                $student->status = 'finished';
                $student->started_at = $session->started_at;
                $student->finished_at = $session->finished_at;
                $student->score = $session->score;
            } else {
                $student->status = 'in_progress';
                $student->started_at = $session->started_at;
                $student->finished_at = null;
                $student->score = null;
            }

            $events = $session?->proctorEvents ?? collect();
            $student->session_id = $session?->id;
            $student->laptop_seen_at = $session?->laptop_seen_at;
            $student->phone_seen_at = $session?->phone_seen_at;
            $student->proctor_event_count = $events->count();
            $student->latest_proctor_event = $events->sortByDesc('created_at')->first();

            return $student;
        });

        $stats = [
            'total' => $participants->count(),
            'not_started' => $participants->where('status', 'not_started')->count(),
            'in_progress' => $participants->where('status', 'in_progress')->count(),
            'finished' => $participants->where('status', 'finished')->count(),
        ];

        if ($request->ajax() || $request->wantsJson()) {
            $passingGrade = $exam->passing_grade;

            $participantsJson = $participants->mapWithKeys(function ($p) use ($passingGrade) {
                $statusBadge = '';
                if ($p->status === 'in_progress') {
                    $statusBadge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full border border-green-200"><span class="h-2 w-2 rounded-full bg-green-500 inline-block animate-pulse"></span> Sedang Ujian</span>';
                } elseif ($p->status === 'finished') {
                    $statusBadge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-full border border-blue-200"><i class="ph ph-check-circle text-sm"></i> Selesai</span>';
                } else {
                    $statusBadge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full border border-gray-200"><i class="ph ph-clock text-sm"></i> Belum Mulai</span>';
                }

                $scoreClass = 'text-gray-400';
                if ($p->score !== null) {
                    $scoreClass = $p->score >= $passingGrade ? 'text-green-600' : 'text-red-500';
                }

                return [$p->username => [
                    'status' => $p->status,
                    'status_badge' => $statusBadge,
                    'started_at' => $p->started_at ? $p->started_at->format('H:i:s') : '-',
                    'finished_at' => $p->finished_at ? $p->finished_at->format('H:i:s') : '-',
                    'score' => $p->score !== null ? (string) $p->score : '-',
                    'score_class' => $scoreClass,
                    'session_id' => $p->session_id,
                    'laptop_seen_at' => $p->laptop_seen_at?->format('H:i:s'),
                    'phone_seen_at' => $p->phone_seen_at?->format('H:i:s'),
                    'laptop_online' => $p->laptop_seen_at && $p->laptop_seen_at->greaterThan(now()->subSeconds(30)),
                    'phone_online' => $p->phone_seen_at && $p->phone_seen_at->greaterThan(now()->subSeconds(30)),
                    'proctor_event_count' => $p->proctor_event_count,
                ]];
            });

            return response()->json([
                'participants' => $participantsJson,
                'stats' => $stats,
                'time' => now()->format('H:i:s'),
            ]);
        }

        return view('admin.exams.monitor', compact('exam', 'participants', 'stats'));
    }

    public function exportPdf(Exam $exam)
    {
        $exam->load('course', 'classrooms');

        $allSessions = ExamSession::where('exam_id', $exam->id)
            ->with('user')
            ->orderBy('user_id')
            ->orderBy('attempt_number')
            ->get();

        $students = $allSessions->groupBy('user_id');

        $finishedStudents = $students->filter(fn ($sessions) => $sessions->where('finished_at', '!=', null)->isNotEmpty());
        $totalStudents = $finishedStudents->count();
        $lastScores = $finishedStudents->map(fn ($sessions) => $sessions->where('finished_at', '!=', null)->last()->score ?? 0);
        $avgScore = $lastScores->count() > 0 ? round($lastScores->avg(), 1) : 0;
        $passed = $lastScores->filter(fn ($s) => $s >= $exam->passing_grade)->count();
        $failed = $totalStudents - $passed;
        $highest = $lastScores->max() ?? 0;
        $lowest = $lastScores->min() ?? 0;

        $pdf = Pdf::loadView('admin.exports.exam-pdf', compact(
            'exam', 'students', 'avgScore', 'passed', 'failed', 'totalStudents', 'highest', 'lowest'
        ));

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $exam->title);
        $filename = 'Laporan_Nilai_'.$safeName.'.pdf';

        return $pdf->download($filename);
    }

    public function resultsCsv(Exam $exam)
    {
        $exam->load('course', 'classrooms');

        $allSessions = ExamSession::where('exam_id', $exam->id)
            ->with('user')
            ->orderBy('user_id')
            ->orderBy('attempt_number')
            ->get();

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=laporan_nilai_{$exam->id}.csv",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($exam, $allSessions) {
            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['No', 'NIM', 'Nama Mahasiswa', 'Percobaan', 'Waktu Submit', 'Skor', 'Status']);

            $students = $allSessions->groupBy('user_id');
            $no = 1;

            $sanitize = function ($value) {
                return (is_string($value) && preg_match('/^[=\-+\@]/', ltrim($value, " \t\r\n"))) ? "'".$value : $value;
            };

            foreach ($students as $userId => $sessions) {
                foreach ($sessions as $session) {
                    $status = $session->score !== null
                        ? ($session->score >= $exam->passing_grade ? 'LULUS' : 'GAGAL')
                        : '-';
                    $waktu = $session->finished_at
                        ? $session->finished_at->format('Y-m-d H:i:s')
                        : 'Belum Selesai';

                    fputcsv($file, array_map($sanitize, [
                        $no,
                        $session->user->username ?? 'Dihapus',
                        $session->user->name ?? 'Mahasiswa Dihapus',
                        $session->attempt_number,
                        $waktu,
                        $session->score ?? '-',
                        $status,
                    ]));
                }
                $no++;
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function studentReport(Exam $exam, User $user)
    {
        abort_unless($user->role === 'mahasiswa', 403);
        abort_unless($exam->classrooms->contains('id', $user->classroom_id), 403);

        $exam->load('course', 'classrooms');

        $questions = $exam->getQuestions();

        $sessions = ExamSession::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->orderBy('attempt_number')
            ->with('answers.question.options', 'answers.option')
            ->get();

        if ($sessions->isEmpty()) {
            return redirect()->route('admin.exams.results', $exam)
                ->with('error', 'Mahasiswa belum mengerjakan ujian ini.');
        }

        return view('admin.exams.student-report', compact('exam', 'user', 'sessions', 'questions'));
    }

    public function gradeCoding(Request $request, Exam $exam, ExamSession $session)
    {
        abort_unless($session->exam_id === $exam->id, 404);

        $request->validate([
            'scores' => 'nullable|array',
            'scores.*' => 'nullable|numeric|min:0|max:100',
        ]);

        $codingQuestionIds = $exam->getQuestions()
            ->filter(fn ($question) => $question->isCoding())
            ->pluck('id');

        foreach ($request->input('scores', []) as $answerId => $score) {
            $answer = Answer::where('id', $answerId)
                ->where('exam_session_id', $session->id)
                ->whereIn('question_id', $codingQuestionIds)
                ->first();

            if (! $answer) {
                continue;
            }

            if ($score === null || $score === '') {
                $answer->update([
                    'score' => null,
                    'grade_status' => Answer::GRADE_PENDING,
                    'graded_by' => null,
                    'ai_grade_detail' => null,
                ]);
            } else {
                $answer->update([
                    'score' => $score,
                    'grade_status' => Answer::GRADE_GRADED,
                    'graded_by' => Answer::GRADED_BY_MANUAL,
                    'ai_grade_detail' => null,
                ]);
            }
        }

        $this->scoring->refreshSession($session, $exam);

        return back()->with('success', 'Penilaian soal koding berhasil disimpan.');
    }

    /**
     * Nilai jawaban koding memakai AI. Diproses sinkron maksimal 5 jawaban
     * per klik agar request tidak timeout; sisanya ditangani cron.
     */
    public function aiGradeCoding(Request $request, Exam $exam, ExamSession $session, AiCodeGrader $grader)
    {
        abort_unless($session->exam_id === $exam->id, 404);

        $force = $request->boolean('force');

        $codingQuestions = $exam->getQuestions()->filter(fn ($question) => $question->isCoding());

        $query = Answer::where('exam_session_id', $session->id)
            ->whereIn('question_id', $codingQuestions->pluck('id'))
            ->whereNotNull('answer_text')
            ->whereRaw("TRIM(answer_text) <> ''");

        if (! $force) {
            $query->where('grade_status', Answer::GRADE_PENDING);
        }

        $answers = $query->orderBy('id')->limit(5)->get();

        $graded = 0;
        $skippedManual = 0;
        $failed = 0;

        foreach ($answers as $answer) {
            if (trim((string) $answer->answer_text) === '') {
                continue;
            }

            if (($answer->isManuallyGraded() || $answer->isAiGraded()) && ! $force) {
                $skippedManual++;

                continue;
            }

            $question = $codingQuestions->firstWhere('id', $answer->question_id);

            if (! $question) {
                $failed++;

                continue;
            }

            try {
                $result = $grader->grade($question, (string) $answer->answer_text, 25);
            } catch (\Throwable $e) {
                Log::warning('Nilai AI gagal, menunggu koreksi manual.', [
                    'session_id' => $session->id,
                    'question_id' => $answer->question_id,
                    'message' => $e->getMessage(),
                ]);
                $failed++;

                continue;
            }

            if ($result['score'] === null) {
                Log::warning('Nilai AI dilewati, menunggu koreksi manual.', [
                    'session_id' => $session->id,
                    'question_id' => $answer->question_id,
                    'error' => $result['error'],
                ]);
                $failed++;

                continue;
            }

            $answer->update([
                'grade_status' => Answer::GRADE_GRADED,
                'score' => $result['score'],
                'graded_by' => Answer::GRADED_BY_AI,
                'ai_grade_detail' => [
                    'score' => $result['score'],
                    'feedback' => $result['feedback'],
                    'model' => $result['model'],
                ],
            ]);
            $graded++;
        }

        if ($graded > 0) {
            $this->scoring->refreshSession($session->fresh(), $exam);
        }

        return back()->with('success', "Nilai AI selesai: {$graded} dinilai, {$skippedManual} dilewati, {$failed} gagal/menunggu manual.");
    }

    public function runCode(Request $request, Exam $exam, CodeRunnerService $runner)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'code' => 'required|string|max:20000',
            'stdin' => 'nullable|string|max:10000',
        ]);

        $question = $exam->getQuestions()->firstWhere('id', (int) $request->question_id);

        if (! $question || ! $question->isCoding()) {
            return response()->json(['success' => false, 'message' => 'Soal tidak valid.'], 400);
        }

        $result = $runner->run(
            (string) $question->code_language,
            (string) $request->input('code'),
            (string) $request->input('stdin', '')
        );

        return response()->json([
            'success' => true,
            'ok' => $result['ok'],
            'compile_output' => $result['compile_output'],
            'output' => $result['output'],
            'stdout' => $result['stdout'],
            'needs_input' => $result['needs_input'],
            'message' => $result['error'],
        ]);
    }
}
