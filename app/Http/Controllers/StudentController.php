<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();

        $exams = Exam::where('classroom_id', $user->classroom_id)
            ->where('is_active', true)
            ->where('end_time', '>', now())
            ->with(['course', 'module'])
            ->orderBy('start_time', 'asc')
            ->get();

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

    public function show(Exam $exam)
    {
        $user = auth()->user();

        // BUG #15 fix: cek classroom, is_active, DAN end_time agar ujian expired tidak bisa diakses
        if ($exam->classroom_id !== $user->classroom_id || !$exam->is_active || now() > $exam->end_time) {
            abort(403, 'Anda tidak memiliki akses ke ujian ini.');
        }

        $lastSession = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->orderByDesc('attempt_number')
            ->first();

        // BUG #16 fix: sinkronkan logika canRemedial — konsisten dengan method Exam::canRemedial()
        // Syarat: (1) ada session terakhir, (2) sudah selesai, (3) nilai < passing_grade,
        //         (4) masih ada sisa percobaan, (5) max_attempts > 1
        $canRemedial = $lastSession
            && $lastSession->finished_at
            && $lastSession->score < $exam->passing_grade
            && $lastSession->attempt_number < $exam->max_attempts
            && $exam->max_attempts > 1;

        $hasUnfinished = $lastSession && !$lastSession->finished_at;

        return view('student.exams.show', compact('exam', 'lastSession', 'canRemedial', 'hasUnfinished'));
    }

    public function start(Exam $exam)
    {
        $user = auth()->user();

        // BUG #3 fix: validasi akses lengkap
        if ($exam->classroom_id !== $user->classroom_id || !$exam->is_active || now() < $exam->start_time || now() > $exam->end_time) {
            abort(403, 'Akses ditolak.');
        }

        if ($exam->getQuestionsCount() === 0) {
            return redirect()->route('student.dashboard')->with('error', 'Ujian ini belum memiliki soal. Hubungi pengawas.');
        }

        // BUG #1 fix: gunakan DB transaction agar atomic, cegah race condition double-start
        try {
            return DB::transaction(function () use ($user, $exam) {
                // Lock row agar tidak ada race condition
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
            // UNIQUE constraint violation = double submit, redirect ke attempt yang sudah ada
            return redirect()->route('student.exams.attempt', $exam);
        }
    }

    public function attempt(Exam $exam)
    {
        $user = auth()->user();

        // BUG #3 fix: cek classroom, is_active, start_time, DAN end_time
        if ($exam->classroom_id !== $user->classroom_id || !$exam->is_active || now() < $exam->start_time) {
            abort(403, 'Anda tidak berhak mengakses ujian ini.');
        }

        // BUG #8 fix: jika tidak ada session aktif, redirect balik dengan pesan jelas
        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (!$session) {
            // Cek apakah ada session yang sudah selesai
            $finished = ExamSession::where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->whereNotNull('finished_at')
                ->exists();
            if ($finished) {
                return redirect()->route('student.dashboard')->with('error', 'Ujian Anda sudah dikumpulkan.');
            }
            return redirect()->route('student.exams.show', $exam)->with('error', 'Silakan mulai ujian terlebih dahulu.');
        }

        // Calculate remaining time
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

        // BUG #9 (sedang) partial-fix: seed shuffle dengan user+exam+attempt agar konsisten per sesi
        $seed = $user->id * 1000 + $exam->id * 100 + $session->attempt_number;
        srand($seed);
        $questions = $questions->shuffle();
        srand(); // reset ke random normal

        foreach ($questions as $question) {
            // Seed per-question untuk opsi
            srand($seed + $question->id);
            $options = $question->options->shuffle();
            srand();
            $question->setRelation('options', $options);
        }

        $existingAnswers = Answer::where('exam_session_id', $session->id)->pluck('option_id', 'question_id')->toArray();

        return view('student.exams.attempt', compact('exam', 'session', 'endTime', 'existingAnswers', 'questions'));
    }

    public function saveAnswer(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if ($exam->classroom_id !== $user->classroom_id || !$exam->is_active) {
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

        // BUG #10 fix: jangan panggil getQuestions() yang meload semua soal beserta opsi ke memory
        
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

        if ($exam->classroom_id !== $user->classroom_id || !$exam->is_active) {
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

        $session->increment('tab_switches');

        $limit = $exam->max_tab_switches;
        $current = $session->tab_switches;
        $exceeded = $limit && $current > $limit;

        return response()->json([
            'success' => true,
            'tab_switches' => $current,
            'max_tab_switches' => $limit,
            'exceeded' => $exceeded,
        ]);
    }

    public function submit(Request $request, Exam $exam)
    {
        $user = auth()->user();

        // BUG #8 fix: jika session sudah finished (double submit), redirect bersih
        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (!$session) {
            return redirect()->route('student.dashboard')->with('success', 'Ujian Anda sudah berhasil dikumpulkan.');
        }

        return $this->processSubmission($request, $session, $exam);
    }

    private function processSubmission(Request $request, ExamSession $session, Exam $exam)
    {
        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        // Allow 30 seconds grace period for network delays
        if (now() > $endTime->copy()->addSeconds(30)) {
            return $this->autoSubmit($session, $exam, 'time');
        }

        // BUG #2 fix: simpan jawaban dari form POST ke DB terlebih dahulu,
        // lalu hitung skor DARI DB — bukan dari data form mentah.
        // Ini memastikan jawaban auto-save sebelumnya tidak tertimpa oleh partial form data.
        $formAnswers = $request->input('answers', []);
        $questions   = $exam->getQuestions();

        // Simpan jawaban form yang masuk (bila ada)
        foreach ($questions as $question) {
            $selectedOptionId = $formAnswers[$question->id] ?? null;
            if ($selectedOptionId) {
                // Validasi option milik question ini
                $valid = $question->options->where('id', $selectedOptionId)->isNotEmpty();
                if ($valid) {
                    Answer::updateOrCreate(
                        ['exam_session_id' => $session->id, 'question_id' => $question->id],
                        ['option_id' => $selectedOptionId]
                    );
                }
            }
        }

        // Hitung skor dari database (source of truth), bukan dari $formAnswers
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
