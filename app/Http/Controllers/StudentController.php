<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    private function loadScheduleForUser(Exam $exam, $user)
    {
        if (!$exam->relationLoaded('classrooms')) {
            $exam->load('classrooms');
        }
        $schedule = $exam->getScheduleForClassroom($user->classroom_id);
        if (!$schedule) return null;
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

        $exams = Exam::whereHas('classrooms', fn($q) => $q->where('classroom_id', $user->classroom_id)->where('is_active', true))
            ->where('is_active', true)
            ->with(['course', 'module', 'classrooms'])
            ->get()
            ->filter(fn($exam) => $this->loadScheduleForUser($exam, $user) && $exam->end_time > now())
            ->sortBy(fn($e) => $e->start_time)
            ->values();

        $examIds = $exams->pluck('id');

        $moduleIds = $exams->pluck('module_id')->filter()->unique();
        $moduleQuestionCounts = \App\Models\Question::whereIn('module_id', $moduleIds)
            ->selectRaw('module_id, COUNT(*) as count')
            ->groupBy('module_id')
            ->pluck('count', 'module_id');
        $directExamIds = $exams->whereNull('module_id')->pluck('id');
        $examQuestionCounts = $directExamIds->isNotEmpty()
            ? \App\Models\Question::whereIn('exam_id', $directExamIds)
                ->selectRaw('exam_id, COUNT(*) as count')
                ->groupBy('exam_id')
                ->pluck('count', 'exam_id')
            : collect();

        $allSessions = ExamSession::where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->orderBy('attempt_number')
            ->get()
            ->groupBy('exam_id');

        $exams->map(function ($exam) use ($user, $allSessions, $moduleQuestionCounts, $examQuestionCounts) {
            $exam->questions_count = $exam->module_id
                ? ($moduleQuestionCounts[$exam->module_id] ?? 0)
                : ($examQuestionCounts[$exam->id] ?? $exam->getQuestionsCount());

            $sessions = $allSessions->get($exam->id, collect());
            $lastSession = $sessions->last();

            if ($lastSession && !$lastSession->finished_at) {
                $exam->session = $lastSession;
                $exam->status  = 'in_progress';
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

        $exam = $examSession->exam;

        if (!$exam) {
            return redirect()->route('student.history')->with('error', 'Data ujian tidak ditemukan.');
        }

        $questions = $exam->getQuestions();

        $seed = $user->id . '_' . $exam->id . '_' . $examSession->attempt_number;
        $questions = $questions->sortBy(fn($q) => crc32($seed . '_q_' . $q->id))->values();

        foreach ($questions as $question) {
            $options = $question->options->sortBy(fn($o) => crc32($seed . '_o_' . $question->id . '_' . $o->id))->values();
            $question->setRelation('options', $options);
        }

        $answers = $examSession->answers()->with('option')->get()->keyBy('question_id');

        $totalQuestions = $questions->count();
        $correctCount = $answers->filter(fn($a) => $a->option && $a->option->is_correct)->count();

        return view('student.history.review', compact('examSession', 'exam', 'questions', 'answers', 'correctCount', 'totalQuestions'));
    }

    public function show(Exam $exam)
    {
        $user = auth()->user();

        if (!$this->loadScheduleForUser($exam, $user)) {
            abort(403, 'Anda tidak memiliki akses ke ujian ini.');
        }

        if (!$exam->is_active || !$exam->isClassroomActive($user->classroom_id) || now() > $exam->end_time) {
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

        $hasUnfinished = $lastSession && !$lastSession->finished_at;

        $pinKey = 'exam_pin_' . $exam->id . '_' . $user->classroom_id;
        $needsPin = $exam->hasPin($user->classroom_id) && session($pinKey) !== $this->getClassroomPin($exam, $user->classroom_id);

        return view('student.exams.show', compact('exam', 'lastSession', 'canRemedial', 'hasUnfinished', 'needsPin'));
    }

    public function verifyPin(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if (!$this->loadScheduleForUser($exam, $user)) {
            abort(403, 'Anda tidak memiliki akses ke ujian ini.');
        }

        $expectedPin = $this->getClassroomPin($exam, $user->classroom_id);

        if (!$expectedPin) {
            return redirect()->route('student.exams.show', $exam);
        }

        $request->validate([
            'pin' => 'required|string|max:10',
        ]);

        if ($request->pin === $expectedPin) {
            $pinKey = 'exam_pin_' . $exam->id . '_' . $user->classroom_id;
            session([$pinKey => $expectedPin]);
            return redirect()->route('student.exams.show', $exam)->with('success', 'PIN benar, silakan mulai ujian.');
        }

        return redirect()->route('student.exams.show', $exam)->with('error', 'PIN salah. Silakan coba lagi.')->with('pin_error', true);
    }

    public function start(Exam $exam)
    {
        $user = auth()->user();

        if (!$this->loadScheduleForUser($exam, $user)) {
            abort(403, 'Akses ditolak.');
        }

        if (!$exam->is_active || !$exam->isClassroomActive($user->classroom_id) || now() < $exam->start_time || now() > $exam->end_time) {
            abort(403, 'Akses ditolak.');
        }

        $expectedPin = $this->getClassroomPin($exam, $user->classroom_id);
        $pinKey = 'exam_pin_' . $exam->id . '_' . $user->classroom_id;
        if ($expectedPin && session($pinKey) !== $expectedPin) {
            return redirect()->route('student.exams.show', $exam)->with('error', 'Silakan masukkan PIN ujian terlebih dahulu.');
        }

        if ($exam->getQuestionsCount() === 0) {
            return redirect()->route('student.dashboard')->with('error', 'Ujian ini belum memiliki soal. Hubungi pengawas.');
        }

        try {
            return DB::transaction(function () use ($user, $exam) {
                $lastSession = ExamSession::where('user_id', $user->id)
                    ->where('exam_id', $exam->id)
                    ->orderByDesc('attempt_number')
                    ->lockForUpdate()
                    ->first();

                if ($lastSession && !$lastSession->finished_at) {
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

                ExamSession::create([
                    'user_id'        => $user->id,
                    'exam_id'        => $exam->id,
                    'attempt_number' => $nextAttempt,
                    'started_at'     => now(),
                ]);

                return redirect()->route('student.exams.attempt', $exam);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::error('Exam start failed: ' . $e->getMessage(), [
                'user_id' => $user->id, 'exam_id' => $exam->id
            ]);
            return redirect()->route('student.dashboard')
                ->with('error', 'Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function attempt(Exam $exam)
    {
        $user = auth()->user();

        if (!$this->loadScheduleForUser($exam, $user)) {
            abort(403, 'Anda tidak berhak mengakses ujian ini.');
        }

        if (!$exam->is_active || now() < $exam->start_time || now() > $exam->end_time) {
            abort(403, 'Anda tidak berhak mengakses ujian ini.');
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (!$session) {
            $finished = ExamSession::where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->whereNotNull('finished_at')
                ->exists();
            if ($finished) {
                return redirect()->route('student.dashboard')->with('error', 'Ujian Anda sudah dikumpulkan.');
            }
            return redirect()->route('student.exams.show', $exam)->with('error', 'Silakan mulai ujian terlebih dahulu.');
        }

        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() >= $endTime) {
            return $this->autoSubmit($session, $exam);
        }

        if ($exam->max_tab_switches && $session->tab_switches > $exam->max_tab_switches) {
            return $this->autoSubmit($session, $exam, 'tab_switch');
        }

        $questions = $exam->getQuestions();

        $seed = $user->id . '_' . $exam->id . '_' . $session->attempt_number;
        $questions = $questions->sortBy(fn($q) => crc32($seed . '_q_' . $q->id))->values();

        foreach ($questions as $question) {
            $options = $question->options->sortBy(fn($o) => crc32($seed . '_o_' . $question->id . '_' . $o->id))->values();
            $question->setRelation('options', $options);
        }

        $existingAnswers = Answer::where('exam_session_id', $session->id)->pluck('option_id', 'question_id')->toArray();

        return view('student.exams.attempt', compact('exam', 'session', 'endTime', 'existingAnswers', 'questions'));
    }

    public function saveAnswer(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if (!$this->loadScheduleForUser($exam, $user)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (!$exam->is_active) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Tidak ada sesi ujian aktif.'], 400);
        }

        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() >= $endTime) {
            return response()->json(['success' => false, 'message' => 'Waktu ujian telah habis.'], 403);
        }

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'option_id' => 'nullable|exists:options,id',
        ]);

        $isValidQuestion = false;
        if ($exam->module_id) {
            $isValidQuestion = \App\Models\Question::where('id', $request->question_id)
                ->where('module_id', $exam->module_id)
                ->exists();
        } else {
            $isValidQuestion = \App\Models\Question::where('id', $request->question_id)
                ->where('exam_id', $exam->id)
                ->exists();
        }

        if (!$isValidQuestion) {
            return response()->json(['success' => false, 'message' => 'Soal tidak valid.'], 400);
        }

        if ($request->option_id) {
            $optionExists = \App\Models\Option::where('id', $request->option_id)
                ->where('question_id', $request->question_id)
                ->exists();

            if (!$optionExists) {
                return response()->json(['success' => false, 'message' => 'Opsi tidak valid untuk soal ini.'], 400);
            }

            Answer::updateOrCreate(
                ['exam_session_id' => $session->id, 'question_id' => $request->question_id],
                ['option_id' => $request->option_id]
            );
        } else {
            Answer::where('exam_session_id', $session->id)
                ->where('question_id', $request->question_id)
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    public function reportTabSwitch(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if (!$this->loadScheduleForUser($exam, $user)) {
            return response()->json(['success' => false], 403);
        }

        if (!$exam->is_active) {
            return response()->json(['success' => false], 403);
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (!$session) {
            return response()->json(['success' => false], 400);
        }

        if (!$exam->max_tab_switches && !$exam->require_fullscreen) {
            return response()->json(['success' => false, 'message' => 'Tab switch detection disabled'], 400);
        }

        if ($request->has('total_switches') && is_numeric($request->total_switches)) {
            $newTotal = max($session->tab_switches, (int) $request->total_switches);
            $session->update(['tab_switches' => $newTotal]);
        } else {
            $session->increment('tab_switches');
        }

        $limit = $exam->max_tab_switches;
        $current = $session->fresh()->tab_switches;
        $exceeded = $limit && $current > $limit;

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

        if (!$this->loadScheduleForUser($exam, $user)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if (!$exam->is_active) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak ditemukan.'], 404);
        }

        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() >= $endTime->copy()->addSeconds(60)) {
            return response()->json(['success' => false, 'message' => 'Waktu ujian telah habis.', 'expired' => true], 403);
        }

        $request->validate([
            'answers' => 'nullable|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.option_id' => 'required|integer',
            'tab_switches' => 'nullable|integer|min:0',
        ]);

        $synced = 0;
        $errors = [];

        DB::transaction(function () use ($request, $session, $exam, &$synced, &$errors) {
            $session = ExamSession::where('id', $session->id)->lockForUpdate()->first();
            if (!$session || $session->finished_at) {
                $errors[] = 'Sesi ujian telah berakhir.';
                return;
            }
            foreach ($request->input('answers', []) as $item) {
                $valid = $exam->module_id
                    ? \App\Models\Question::where('id', $item['question_id'])->where('module_id', $exam->module_id)->exists()
                    : \App\Models\Question::where('id', $item['question_id'])->where('exam_id', $exam->id)->exists();

                if (!$valid) {
                    $errors[] = "Soal #{$item['question_id']} tidak valid.";
                    continue;
                }

                $optionValid = \App\Models\Option::where('id', $item['option_id'])
                    ->where('question_id', $item['question_id'])
                    ->exists();

                if (!$optionValid) {
                    $errors[] = "Opsi #{$item['option_id']} tidak valid.";
                    continue;
                }

                Answer::updateOrCreate(
                    ['exam_session_id' => $session->id, 'question_id' => $item['question_id']],
                    ['option_id' => $item['option_id']]
                );
                $synced++;
            }

            if ($request->has('tab_switches')) {
                $newTotal = max($session->tab_switches, (int) $request->tab_switches);
                $session->update(['tab_switches' => $newTotal]);
            }
        });

        $freshSession = $session->fresh();
        $exceeded = $exam->max_tab_switches && $freshSession->tab_switches > $exam->max_tab_switches;

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

        if (!$this->loadScheduleForUser($exam, $user)) {
            return redirect()->route('student.dashboard')->with('error', 'Akses ditolak.');
        }

        return DB::transaction(function () use ($request, $user, $exam) {
            $session = ExamSession::where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->whereNull('finished_at')
                ->orderByDesc('attempt_number')
                ->lockForUpdate()
                ->first();

            if (!$session) {
                return redirect()->route('student.dashboard')->with('success', 'Ujian Anda sudah berhasil dikumpulkan.');
            }

            return $this->processSubmission($request, $session, $exam);
        });
    }

    private function processSubmission(Request $request, ExamSession $session, Exam $exam)
    {
        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() > $endTime->copy()->addSeconds(30)) {
            return $this->autoSubmit($session, $exam, 'time');
        }

        $formAnswers = $request->input('answers', []);
        $questions   = $exam->getQuestions();

        foreach ($questions as $question) {
            $selectedOptionId = $formAnswers[$question->id] ?? null;
            if ($selectedOptionId) {
                $valid = $question->options->where('id', $selectedOptionId)->isNotEmpty();
                if ($valid) {
                    Answer::updateOrCreate(
                        ['exam_session_id' => $session->id, 'question_id' => $question->id],
                        ['option_id' => $selectedOptionId]
                    );
                }
            }
        }

        $totalQuestions = $questions->count();
        $correctCount   = 0;

        $savedAnswers = Answer::where('exam_session_id', $session->id)
            ->with('option')
            ->get();

        foreach ($savedAnswers as $answer) {
            if ($answer->option && $answer->option->is_correct) {
                $correctCount++;
            }
        }

        $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

        $session->update([
            'finished_at' => now(),
            'score'       => $score,
        ]);

        return redirect()->route('student.dashboard')->with('success', 'Ujian berhasil diselesaikan.');
    }

    private function autoSubmit(ExamSession $session, Exam $exam, string $reason = 'time')
    {
        $correctCount = 0;

        $totalQuestions = $exam->getQuestionsCount();

        $answers = Answer::where('exam_session_id', $session->id)->with('option')->get();
        foreach ($answers as $answer) {
            if ($answer->option && $answer->option->is_correct) {
                $correctCount++;
            }
        }

        $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

        $session->update([
            'finished_at' => now(),
            'score'       => $score,
        ]);

        $message = $reason === 'tab_switch'
            ? 'Terlalu banyak pindah tab, ujian otomatis diselesaikan.'
            : 'Waktu habis, ujian otomatis diselesaikan.';

        return redirect()->route('student.dashboard')->with('success', $message);
    }
}
