<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Answer;
use Illuminate\Http\Request;

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
        $allSessions = ExamSession::where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->orderBy('attempt_number')
            ->get()
            ->groupBy('exam_id');

        $exams->map(function ($exam) use ($user, $allSessions) {
            $exam->questions_count = $exam->getQuestionsCount();

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
        
        if ($exam->classroom_id !== $user->classroom_id || !$exam->is_active) {
            abort(403, 'Anda tidak memiliki akses ke ujian ini.');
        }

        $lastSession = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->orderByDesc('attempt_number')
            ->first();

        $canRemedial = $lastSession && $lastSession->finished_at
            && $lastSession->score < $exam->passing_grade
            && $lastSession->attempt_number < $exam->max_attempts;

        $hasUnfinished = $lastSession && !$lastSession->finished_at;

        return view('student.exams.show', compact('exam', 'lastSession', 'canRemedial', 'hasUnfinished'));
    }

    public function start(Exam $exam)
    {
        $user = auth()->user();
        
        if ($exam->classroom_id !== $user->classroom_id || !$exam->is_active || now() < $exam->start_time || now() > $exam->end_time) {
            abort(403, 'Akses ditolak.');
        }

        if ($exam->getQuestionsCount() === 0) {
            return redirect()->route('student.dashboard')->with('error', 'Ujian ini belum memiliki soal. Hubungi pengawas.');
        }

        $lastSession = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->orderByDesc('attempt_number')
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

        $session = ExamSession::create([
            'user_id'        => $user->id,
            'exam_id'        => $exam->id,
            'started_at'     => now(),
            'attempt_number' => $nextAttempt,
        ]);

        return redirect()->route('student.exams.attempt', $exam);
    }

    public function attempt(Exam $exam)
    {
        $user = auth()->user();

        if ($exam->classroom_id !== $user->classroom_id) {
            abort(403, 'Anda tidak berhak mengakses ujian ini.');
        }

        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->firstOrFail();

        // Calculate remaining time
        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() >= $endTime) {
            return $this->autoSubmit($session, $exam);
        }

        $questions = $exam->getQuestions();

        $questions = $questions->shuffle();

        foreach ($questions as $question) {
            $options = $question->options->shuffle();
            $question->setRelation('options', $options);
        }

        $existingAnswers = Answer::where('exam_session_id', $session->id)->pluck('option_id', 'question_id')->toArray();

        return view('student.exams.attempt', compact('exam', 'session', 'endTime', 'existingAnswers', 'questions'));
    }

    public function submit(Request $request, Exam $exam)
    {
        $user = auth()->user();
        $session = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->firstOrFail();

        return $this->processSubmission($request, $session, $exam);
    }

    private function processSubmission(Request $request, ExamSession $session, Exam $exam)
    {
        $answers = $request->input('answers', []);

        $correctCount = 0;

        $questions = $exam->getQuestions();

        $totalQuestions = $questions->count();

        foreach ($questions as $question) {
            $selectedOptionId = $answers[$question->id] ?? null;

            if ($selectedOptionId) {
                Answer::updateOrCreate(
                    ['exam_session_id' => $session->id, 'question_id' => $question->id],
                    ['option_id' => $selectedOptionId]
                );

                $option = $question->options->where('id', $selectedOptionId)->first();
                if ($option && $option->is_correct) {
                    $correctCount++;
                }
            }
        }

        $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

        $session->update([
            'finished_at' => now(),
            'score'       => $score,
        ]);

        return redirect()->route('student.dashboard')->with('success', 'Ujian berhasil diselesaikan.');
    }

    private function autoSubmit(ExamSession $session, Exam $exam)
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

        return redirect()->route('student.dashboard')->with('success', 'Waktu habis, ujian otomatis diselesaikan.');
    }
}
