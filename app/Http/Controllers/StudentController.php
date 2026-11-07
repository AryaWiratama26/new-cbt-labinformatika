<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Option;
use App\Models\Question;
use App\Services\CodeRunnerService;
use App\Services\ExamScoringService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    public function __construct(private ExamScoringService $scoring) {}

    /**
     * Tenggang waktu setelah batas akhir agar jawaban terakhir
     * yang sedang dikirim tetap diterima (sama untuk semua endpoint).
     */
    private const SUBMIT_GRACE_SECONDS = 60;

    private function loadScheduleForUser(Exam $exam, $user)
    {
        if (! $exam->relationLoaded('classrooms')) {
            $exam->load('classrooms');
        }
        $schedule = $exam->getScheduleForClassroom($user->classroom_id);
        if (! $schedule) {
            return null;
        }
        $exam->start_time = $schedule->start_time;
        $exam->end_time = $schedule->end_time;
        $exam->duration_minutes = $schedule->duration_minutes;

        return $schedule;
    }

    private function getClassroomPin(Exam $exam, $classroomId): ?string
    {
        if ($exam->relationLoaded('classrooms')) {
            return $exam->classrooms->firstWhere('id', $classroomId)?->pivot?->pin;
        }

        return $exam->classrooms()
            ->where('classroom_id', $classroomId)
            ->first()?->pivot?->pin;
    }

    public function dashboard()
    {
        $user = auth()->user();

        $exams = Exam::whereHas('classrooms', fn ($q) => $q->where('classroom_id', $user->classroom_id)->where('is_active', true))
            ->where('is_active', true)
            ->with(['course', 'module', 'classrooms'])
            ->get()
            ->filter(fn ($exam) => $this->loadScheduleForUser($exam, $user) && $exam->end_time > now())
            ->sortBy(fn ($e) => $e->start_time)
            ->values();

        $examIds = $exams->pluck('id');

        $moduleIds = $exams->pluck('module_id')->filter()->unique();
        $moduleQuestionCounts = Question::whereIn('module_id', $moduleIds)
            ->selectRaw('module_id, COUNT(*) as count')
            ->groupBy('module_id')
            ->pluck('count', 'module_id');
        $directExamIds = $exams->whereNull('module_id')->pluck('id');
        $examQuestionCounts = $directExamIds->isNotEmpty()
            ? Question::whereIn('exam_id', $directExamIds)
                ->selectRaw('exam_id, COUNT(*) as count')
                ->groupBy('exam_id')
                ->pluck('count', 'exam_id')
            : collect();

        $allSessions = ExamSession::where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->orderBy('attempt_number')
            ->get()
            ->groupBy('exam_id');

        $exams->map(function ($exam) use ($allSessions, $moduleQuestionCounts, $examQuestionCounts) {
            $exam->questions_count = $exam->module_id
                ? ($moduleQuestionCounts[$exam->module_id] ?? 0)
                : ($examQuestionCounts[$exam->id] ?? $exam->getQuestionsCount());

            $sessions = $allSessions->get($exam->id, collect());
            $lastSession = $sessions->last();

            if ($lastSession && ! $lastSession->finished_at) {
                $exam->session = $lastSession;
                $exam->status = 'in_progress';
                $exam->attempt_number = $lastSession->attempt_number;
            } elseif ($lastSession && $lastSession->finished_at) {
                $exam->session = $lastSession;
                $exam->attempt_number = $lastSession->attempt_number;
                $exam->max_attempts = $exam->max_attempts ?? 1;

                if ($lastSession->score < $exam->passing_grade && $lastSession->attempt_number < $exam->max_attempts) {
                    $exam->status = 'remedial';
                } else {
                    $exam->status = 'finished';
                }
            } else {
                $exam->session = null;
                $exam->attempt_number = 0;
                $exam->status = now() < $exam->start_time ? 'waiting' : 'available';
            }

            $exam->total_attempts = $sessions->count();

            return $exam;
        });

        return view('student.dashboard', compact('exams'));
    }

    public function history()
    {
        $user = auth()->user();

        $sessions = ExamSession::where('user_id', $user->id)
            ->whereNotNull('finished_at')
            ->with(['exam.course', 'exam.classrooms'])
            ->orderBy('finished_at', 'desc')
            ->paginate(20);

        return view('student.history.index', compact('sessions'));
    }

    public function reviewSession(ExamSession $examSession)
    {
        $user = auth()->user();

        if ((int) $examSession->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($examSession->finished_at === null) {
            return redirect()->route('student.dashboard')->with('error', 'Ujian belum selesai, pembahasan belum tersedia.');
        }

        $exam = $examSession->exam;

        if (! $exam) {
            return redirect()->route('student.history')->with('error', 'Data ujian tidak ditemukan.');
        }

        $questions = $exam->getQuestions();

        $seed = $user->id.'_'.$exam->id.'_'.$examSession->attempt_number;
        $questions = $questions->sortBy(fn ($q) => crc32($seed.'_q_'.$q->id))->values();

        foreach ($questions as $question) {
            $options = $question->options->sortBy(fn ($o) => crc32($seed.'_o_'.$question->id.'_'.$o->id))->values();
            $question->setRelation('options', $options);
        }

        $answers = $examSession->answers()->with('option')->get()->keyBy('question_id');

        $totalQuestions = $questions->count();
        $correctCount = $answers->filter(fn ($a) => $a->option && $a->option->is_correct)->count();

        return view('student.history.review', compact('examSession', 'exam', 'questions', 'answers', 'correctCount', 'totalQuestions'));
    }

    public function gradeStatus(ExamSession $examSession)
    {
        $user = auth()->user();

        if ((int) $examSession->user_id !== (int) $user->id) {
            abort(403);
        }

        $codingIds = $examSession->exam
            ? $examSession->exam->getQuestions()->filter(fn ($q) => $q->isCoding())->pluck('id')
            : collect();

        $pending = $examSession->answers()
            ->whereIn('question_id', $codingIds)
            ->whereNotNull('answer_text')
            ->whereRaw("TRIM(answer_text) <> ''")
            ->where('grade_status', '<>', Answer::GRADE_GRADED)
            ->count();

        return response()->json([
            'finished' => $examSession->finished_at !== null,
            'graded' => $examSession->graded_at !== null,
            'score' => $examSession->score,
            'pending' => $pending,
        ]);
    }

    public function show(Exam $exam)
    {
        $user = auth()->user();

        if (! $this->loadScheduleForUser($exam, $user)) {
            abort(403, 'Anda tidak memiliki akses ke ujian ini.');
        }

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id) || now() > $exam->end_time) {
            abort(403, 'Anda tidak memiliki akses ke ujian ini.');
        }

        $lastSession = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->orderByDesc('attempt_number')
            ->first();

        $canRemedial = $lastSession
            && $lastSession->finished_at
            && $lastSession->score < $exam->passing_grade
            && $lastSession->attempt_number < $exam->max_attempts
            && $exam->max_attempts > 1;

        $hasUnfinished = $lastSession && ! $lastSession->finished_at;

        $pinKey = 'exam_pin_'.$exam->id.'_'.$user->classroom_id;
        $needsPin = $exam->hasPin($user->classroom_id) && session($pinKey) !== $this->getClassroomPin($exam, $user->classroom_id);

        return view('student.exams.show', compact('exam', 'lastSession', 'canRemedial', 'hasUnfinished', 'needsPin'));
    }

    public function verifyPin(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if (! $this->loadScheduleForUser($exam, $user)) {
            abort(403, 'Anda tidak memiliki akses ke ujian ini.');
        }

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id) || now() < $exam->start_time || now() > $exam->end_time) {
            abort(403, 'Anda tidak memiliki akses ke ujian ini.');
        }

        $expectedPin = $this->getClassroomPin($exam, $user->classroom_id);

        if (! $expectedPin) {
            return redirect()->route('student.exams.show', $exam);
        }

        $request->validate([
            'pin' => 'required|string|max:10',
        ]);

        if ($request->pin === $expectedPin) {
            $pinKey = 'exam_pin_'.$exam->id.'_'.$user->classroom_id;
            session([$pinKey => $expectedPin]);

            return redirect()->route('student.exams.show', $exam)->with('success', 'PIN benar, silakan mulai ujian.');
        }

        return redirect()->route('student.exams.show', $exam)->with('error', 'PIN salah. Silakan coba lagi.')->with('pin_error', true);
    }

    public function start(Exam $exam)
    {
        $user = auth()->user();

        if (! $this->loadScheduleForUser($exam, $user)) {
            abort(403, 'Akses ditolak.');
        }

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id) || now() < $exam->start_time || now() > $exam->end_time) {
            abort(403, 'Akses ditolak.');
        }

        $expectedPin = $this->getClassroomPin($exam, $user->classroom_id);
        $pinKey = 'exam_pin_'.$exam->id.'_'.$user->classroom_id;
        if ($expectedPin && session($pinKey) !== $expectedPin) {
            return redirect()->route('student.exams.show', $exam)->with('error', 'Silakan masukkan PIN ujian terlebih dahulu.');
        }

        if ($exam->getQuestionsCount() === 0) {
            return redirect()->route('student.dashboard')->with('error', 'Ujian ini belum memiliki soal. Hubungi pengawas.');
        }

        $activeSession = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->exists();

        if ($exam->camera_proctoring && $exam->camera_phone && ! $activeSession) {
            $token = Cache::get("proctor_pair_pending:{$user->id}:{$exam->id}");
            $pairData = $token ? Cache::get("proctor_pair:{$token}") : null;

            if (! $pairData || empty($pairData['paired'])) {
                return redirect()->route('student.exams.show', $exam)
                    ->with('error', 'Scan QR kamera HP terlebih dahulu sebelum memulai ujian.');
            }
        }

        if ($exam->camera_proctoring && ! $activeSession && ! session("camera_ready:{$exam->id}")) {
            return redirect()->route('student.exams.show', $exam)
                ->with('error', 'Aktifkan kamera laptop terlebih dahulu sebelum memulai ujian.');
        }

        try {
            return DB::transaction(function () use ($user, $exam) {
                $lastSession = ExamSession::where('user_id', $user->id)
                    ->where('exam_id', $exam->id)
                    ->orderByDesc('attempt_number')
                    ->lockForUpdate()
                    ->first();

                if ($lastSession && ! $lastSession->finished_at) {
                    return redirect()->route('student.exams.attempt', $exam);
                }

                $nextAttempt = $lastSession ? $lastSession->attempt_number + 1 : 1;

                if ($nextAttempt > 1) {
                    if ($lastSession->score >= $exam->passing_grade) {
                        return redirect()->route('student.dashboard')->with('error', 'Anda sudah lulus ujian ini.');
                    }
                    if ($nextAttempt > $exam->max_attempts) {
                        return redirect()->route('student.dashboard')->with('error', 'Batas percobaan ujian telah habis.');
                    }
                }

                $session = ExamSession::create([
                    'user_id' => $user->id,
                    'exam_id' => $exam->id,
                    'attempt_number' => $nextAttempt,
                    'started_at' => now(),
                ]);

                if ($exam->camera_proctoring && $exam->camera_phone) {
                    $token = Cache::get("proctor_pair_pending:{$user->id}:{$exam->id}");
                    if ($token) {
                        $pairKey = "proctor_pair:{$token}";
                        $pairData = Cache::get($pairKey);
                        if ($pairData) {
                            $pairData['session_id'] = $session->id;
                            Cache::put($pairKey, $pairData, now()->addMinutes(5));
                        }
                    }
                }

                return redirect()->route('student.exams.attempt', $exam);
            });
        } catch (QueryException $e) {
            Log::error('Exam start failed: '.$e->getMessage(), [
                'user_id' => $user->id, 'exam_id' => $exam->id,
            ]);

            return redirect()->route('student.dashboard')
                ->with('error', 'Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function attempt(Exam $exam)
    {
        $user = auth()->user();

        if (! $this->loadScheduleForUser($exam, $user)) {
            abort(403, 'Anda tidak berhak mengakses ujian ini.');
        }

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id) || now() < $exam->start_time || now() > $exam->end_time) {
            abort(403, 'Anda tidak berhak mengakses ujian ini.');
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (! $session) {
            $finished = ExamSession::where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->whereNotNull('finished_at')
                ->exists();
            if ($finished) {
                return redirect()->route('student.dashboard')->with('error', 'Ujian Anda sudah dikumpulkan.');
            }

            return redirect()->route('student.exams.show', $exam)->with('error', 'Silakan mulai ujian terlebih dahulu.');
        }

        if ($exam->camera_proctoring && ! session("camera_ready:{$exam->id}")) {
            return redirect()->route('student.exams.show', $exam)
                ->with('error', 'Aktifkan kamera laptop terlebih dahulu sebelum melanjutkan ujian.');
        }

        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() >= $endTime) {
            return $this->autoSubmit($session, $exam);
        }

        if ($exam->max_tab_switches && $session->tab_switches >= $exam->max_tab_switches) {
            return $this->autoSubmit($session, $exam, 'tab_switch');
        }

        $questions = $exam->getQuestions();

        $seed = $user->id.'_'.$exam->id.'_'.$session->attempt_number;
        $questions = $questions->sortBy(fn ($q) => crc32($seed.'_q_'.$q->id))->values();

        foreach ($questions as $question) {
            $options = $question->options->sortBy(fn ($o) => crc32($seed.'_o_'.$question->id.'_'.$o->id))->values();
            $question->setRelation('options', $options);
        }

        $existingAnswers = Answer::where('exam_session_id', $session->id)
            ->whereNotNull('option_id')
            ->pluck('option_id', 'question_id')
            ->toArray();

        $codeAnswers = Answer::where('exam_session_id', $session->id)
            ->whereNotNull('answer_text')
            ->pluck('answer_text', 'question_id')
            ->toArray();

        return view('student.exams.attempt', compact('exam', 'session', 'endTime', 'existingAnswers', 'codeAnswers', 'questions'));
    }

    public function saveAnswer(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if (! $this->loadScheduleForUser($exam, $user)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Tidak ada sesi ujian aktif.'], 400);
        }

        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() >= $endTime->copy()->addSeconds(self::SUBMIT_GRACE_SECONDS)) {
            return response()->json(['success' => false, 'message' => 'Waktu ujian telah habis.', 'expired' => true], 403);
        }

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'option_id' => 'nullable|exists:options,id',
            'answer_text' => 'nullable|string',
        ]);

        $question = $this->findExamQuestion($exam, (int) $request->question_id);

        if (! $question) {
            return response()->json(['success' => false, 'message' => 'Soal tidak valid.'], 400);
        }

        if ($question->isCoding()) {
            $text = $request->input('answer_text');

            return DB::transaction(function () use ($session, $question, $text) {
                if (! $this->lockActiveSession($session->id)) {
                    return response()->json(['success' => false, 'message' => 'Sesi ujian telah selesai.'], 403);
                }

                if ($text === null || trim($text) === '') {
                    Answer::where('exam_session_id', $session->id)
                        ->where('question_id', $question->id)
                        ->delete();
                } else {
                    Answer::updateOrCreate(
                        ['exam_session_id' => $session->id, 'question_id' => $question->id],
                        [
                            'option_id' => null,
                            'answer_text' => $text,
                            'grade_status' => Answer::GRADE_PENDING,
                            'score' => null,
                            'graded_by' => null,
                            'ai_grade_detail' => null,
                        ]
                    );
                }

                return response()->json(['success' => true]);
            });
        }

        if ($request->option_id) {
            $optionExists = Option::where('id', $request->option_id)
                ->where('question_id', $question->id)
                ->exists();

            if (! $optionExists) {
                return response()->json(['success' => false, 'message' => 'Opsi tidak valid untuk soal ini.'], 400);
            }

            return DB::transaction(function () use ($session, $question, $request) {
                if (! $this->lockActiveSession($session->id)) {
                    return response()->json(['success' => false, 'message' => 'Sesi ujian telah selesai.'], 403);
                }

                Answer::updateOrCreate(
                    ['exam_session_id' => $session->id, 'question_id' => $question->id],
                    ['option_id' => $request->option_id, 'answer_text' => null, 'grade_status' => Answer::GRADE_AUTO, 'score' => null]
                );

                return response()->json(['success' => true]);
            });
        }

        return DB::transaction(function () use ($session, $question) {
            if (! $this->lockActiveSession($session->id)) {
                return response()->json(['success' => false, 'message' => 'Sesi ujian telah selesai.'], 403);
            }

            Answer::where('exam_session_id', $session->id)
                ->where('question_id', $question->id)
                ->delete();

            return response()->json(['success' => true]);
        });
    }

    /**
     * Kunci baris sesi dan pastikan masih aktif. Cegah tulis jawaban
     * ke sesi yang sudah di-finish oleh request konkuren (submit/habis waktu).
     */
    private function lockActiveSession(int $sessionId): bool
    {
        return ExamSession::where('id', $sessionId)
            ->whereNull('finished_at')
            ->lockForUpdate()
            ->exists();
    }

    public function runCode(Request $request, Exam $exam, CodeRunnerService $runner)
    {
        $user = auth()->user();

        if (! $this->loadScheduleForUser($exam, $user)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Tidak ada sesi ujian aktif.'], 400);
        }

        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() >= $endTime->copy()->addSeconds(self::SUBMIT_GRACE_SECONDS)) {
            return response()->json(['success' => false, 'message' => 'Waktu ujian telah habis.', 'expired' => true], 403);
        }

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'code' => 'required|string|max:20000',
            'stdin' => 'nullable|string|max:10000',
        ]);

        $question = $this->findExamQuestion($exam, (int) $request->question_id);

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

    public function reportTabSwitch(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if (! $this->loadScheduleForUser($exam, $user)) {
            return response()->json(['success' => false], 403);
        }

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id)) {
            return response()->json(['success' => false], 403);
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (! $session) {
            return response()->json(['success' => false], 400);
        }

        if (! $exam->max_tab_switches && ! $exam->require_fullscreen) {
            return response()->json(['success' => false, 'message' => 'Tab switch detection disabled'], 400);
        }

        if ($request->has('delta') && is_numeric($request->delta)) {
            $delta = max(0, min(1000, (int) $request->delta));
        } elseif ($request->has('total_switches') && is_numeric($request->total_switches)) {
            $delta = max(0, (int) $request->total_switches - $session->tab_switches);
        } else {
            $delta = 1;
        }

        $locked = DB::transaction(function () use ($session, $delta) {
            if (! $this->lockActiveSession($session->id)) {
                return null;
            }

            if ($delta > 0) {
                $session->increment('tab_switches', $delta);
            }

            return $session->fresh()->tab_switches;
        });

        if ($locked === null) {
            return response()->json(['success' => false, 'message' => 'Sesi ujian telah selesai.'], 403);
        }

        $session->tab_switches = $locked;

        $limit = $exam->max_tab_switches;
        $current = $session->fresh()->tab_switches;
        $exceeded = $limit && $current >= $limit;

        return response()->json([
            'success' => true,
            'tab_switches' => $current,
            'max_tab_switches' => $limit,
            'exceeded' => $exceeded,
        ]);
    }

    public function syncAnswers(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if (! $this->loadScheduleForUser($exam, $user)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak ditemukan.'], 404);
        }

        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() >= $endTime->copy()->addSeconds(self::SUBMIT_GRACE_SECONDS)) {
            return response()->json(['success' => false, 'message' => 'Waktu ujian telah habis.', 'expired' => true], 403);
        }

        $request->validate([
            'answers' => 'nullable|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.option_id' => 'nullable|integer',
            'answers.*.answer_text' => 'nullable|string',
            'tab_switches' => 'nullable|integer|min:0',
            'tab_switch_delta' => 'nullable|integer|min:0|max:1000',
        ]);

        $synced = 0;
        $errors = [];

        DB::transaction(function () use ($request, $session, $exam, &$synced, &$errors) {
            $session = ExamSession::where('id', $session->id)->lockForUpdate()->first();
            if (! $session || $session->finished_at) {
                $errors[] = 'Sesi ujian telah berakhir.';

                return;
            }
            foreach ($request->input('answers', []) as $item) {
                $question = $this->findExamQuestion($exam, (int) $item['question_id']);

                if (! $question) {
                    $errors[] = "Soal #{$item['question_id']} tidak valid.";

                    continue;
                }

                if ($question->isCoding()) {
                    $text = $item['answer_text'] ?? null;

                    if ($text === null || trim((string) $text) === '') {
                        Answer::where('exam_session_id', $session->id)
                            ->where('question_id', $question->id)
                            ->delete();
                    } else {
                        Answer::updateOrCreate(
                            ['exam_session_id' => $session->id, 'question_id' => $question->id],
                            [
                                'option_id' => null,
                                'answer_text' => $text,
                                'grade_status' => Answer::GRADE_PENDING,
                                'score' => null,
                                'graded_by' => null,
                                'ai_grade_detail' => null,
                            ]
                        );
                        $synced++;
                    }

                    continue;
                }

                $hasOption = ! empty($item['option_id']);
                $optionValid = $hasOption
                    && Option::where('id', $item['option_id'])
                        ->where('question_id', $question->id)
                        ->exists();

                if (! $optionValid) {
                    $errors[] = 'Opsi #'.($item['option_id'] ?? '-').' tidak valid.';

                    continue;
                }

                Answer::updateOrCreate(
                    ['exam_session_id' => $session->id, 'question_id' => $question->id],
                    ['option_id' => $item['option_id'], 'answer_text' => null]
                );
                $synced++;
            }

            if ($request->has('tab_switch_delta') && is_numeric($request->input('tab_switch_delta'))) {
                $session->increment('tab_switches', max(0, min(1000, (int) $request->input('tab_switch_delta'))));
            } elseif ($request->has('tab_switches')) {
                $newTotal = max($session->tab_switches, (int) $request->tab_switches);
                $session->update(['tab_switches' => $newTotal]);
            }
        });

        $freshSession = $session->fresh();
        $exceeded = $exam->max_tab_switches && $freshSession->tab_switches >= $exam->max_tab_switches;

        return response()->json([
            'success' => true,
            'synced' => $synced,
            'errors' => $errors,
            'tab_switches' => $freshSession->tab_switches,
            'exceeded' => $exceeded,
        ]);
    }

    public function submit(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if (! $this->loadScheduleForUser($exam, $user)) {
            return redirect()->route('student.dashboard')->with('error', 'Akses ditolak.');
        }

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id)) {
            return redirect()->route('student.dashboard')->with('error', 'Akses ditolak.');
        }

        return DB::transaction(function () use ($request, $user, $exam) {
            $session = ExamSession::where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->whereNull('finished_at')
                ->orderByDesc('attempt_number')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                return redirect()->route('student.dashboard')->with('success', 'Ujian Anda sudah berhasil dikumpulkan.');
            }

            if ($exam->camera_phone && $this->isPhoneCameraOff($user->id, $exam->id)) {
                return redirect()->route('student.exams.attempt', $exam)->with('error', 'Kamera HP tidak aktif. Aktifkan kembali kamera HP untuk mengumpulkan ujian.');
            }

            return $this->processSubmission($request, $session, $exam);
        });
    }

    private function processSubmission(Request $request, ExamSession $session, Exam $exam)
    {
        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() > $endTime->copy()->addSeconds(self::SUBMIT_GRACE_SECONDS)) {
            return $this->autoSubmit($session, $exam, 'time');
        }

        $formAnswers = $request->input('answers', []);
        $textAnswers = $request->input('answers_text', []);
        $questions = $exam->getQuestions();

        foreach ($questions as $question) {
            if ($question->isCoding()) {
                $text = $textAnswers[$question->id] ?? null;

                if ($text !== null && trim((string) $text) !== '') {
                    Answer::updateOrCreate(
                        ['exam_session_id' => $session->id, 'question_id' => $question->id],
                        [
                            'option_id' => null,
                            'answer_text' => $text,
                            'grade_status' => Answer::GRADE_PENDING,
                            'score' => null,
                            'graded_by' => null,
                            'ai_grade_detail' => null,
                        ]
                    );
                } elseif (array_key_exists($question->id, $textAnswers)) {
                    Answer::where('exam_session_id', $session->id)
                        ->where('question_id', $question->id)
                        ->delete();
                }

                continue;
            }

            $selectedOptionId = $formAnswers[$question->id] ?? null;
            if ($selectedOptionId) {
                $valid = $question->options->where('id', $selectedOptionId)->isNotEmpty();
                if ($valid) {
                    Answer::updateOrCreate(
                        ['exam_session_id' => $session->id, 'question_id' => $question->id],
                        ['option_id' => $selectedOptionId, 'answer_text' => null]
                    );
                }
            }
        }

        $result = $this->scoring->recompute($session, $exam);

        $session->update([
            'finished_at' => now(),
            'score' => $result['score'],
            'graded_at' => $result['pending'] > 0 ? null : now(),
        ]);

        return redirect()->route('student.dashboard')->with('success', 'Ujian berhasil diselesaikan.');
    }

    private function autoSubmit(ExamSession $session, Exam $exam, string $reason = 'time')
    {
        return DB::transaction(function () use ($session, $exam, $reason) {
            $fresh = ExamSession::where('id', $session->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->finished_at !== null) {
                return redirect()->route('student.dashboard')->with('success', 'Ujian Anda sudah berhasil dikumpulkan.');
            }

            $result = $this->scoring->recompute($fresh, $exam);

            $fresh->update([
                'finished_at' => now(),
                'score' => $result['score'],
                'graded_at' => $result['pending'] > 0 ? null : now(),
            ]);

            $message = $reason === 'tab_switch'
                ? 'Terlalu banyak pindah tab, ujian otomatis diselesaikan.'
                : 'Waktu habis, ujian otomatis diselesaikan.';

            return redirect()->route('student.dashboard')->with('success', $message);
        });
    }

    /**
     * True bila kamera HP dipasangkan tapi status terakhir nonaktif.
     * Tanpa data pairing dianggap tidak diketahui (tidak diblokir).
     */
    private function isPhoneCameraOff(int $userId, int $examId): bool
    {
        $token = Cache::get("proctor_pair_pending:{$userId}:{$examId}");

        if (! $token) {
            return false;
        }

        $data = Cache::get("proctor_pair:{$token}");

        return is_array($data)
            && array_key_exists('phone_camera_active', $data)
            && $data['phone_camera_active'] === false;
    }

    private function findExamQuestion(Exam $exam, int $questionId): ?Question
    {
        return Question::query()
            ->when($exam->module_id, fn ($q) => $q->where('module_id', $exam->module_id))
            ->when(! $exam->module_id, fn ($q) => $q->where('exam_id', $exam->id))
            ->find($questionId);
    }
}
