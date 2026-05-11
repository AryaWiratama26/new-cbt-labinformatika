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

        // Eager load sessions to avoid N+1 (Bug Fix #5)
        $exams = Exam::where('classroom_id', $user->classroom_id)
            ->where('is_active', true)
            ->where('end_time', '>', now())
            ->with(['course', 'module'])
            ->withCount([
                'questions as questions_count' => function ($q) {
                    // count from module if module exists
                },
            ])
            ->orderBy('start_time', 'asc')
            ->get();

        // Load all sessions for this user in one query
        $examIds = $exams->pluck('id');
        $sessions = ExamSession::where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->get()
            ->keyBy('exam_id');

        $exams->map(function ($exam) use ($user, $sessions) {
            // Count questions: prefer module's questions if module exists
            if ($exam->module_id) {
                $exam->questions_count = \App\Models\Question::where('module_id', $exam->module_id)->count();
            }

            $session = $sessions->get($exam->id);
            if ($session) {
                $exam->session = $session;
                $exam->status  = $session->finished_at ? 'finished' : 'in_progress';
            } else {
                $exam->session = null;
                $exam->status  = now() < $exam->start_time ? 'waiting' : 'available';
            }
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

        $session = ExamSession::where('user_id', $user->id)->where('exam_id', $exam->id)->first();
        if ($session && $session->finished_at) {
            return redirect()->route('student.dashboard')->with('error', 'Anda sudah menyelesaikan ujian ini.');
        }

        return view('student.exams.show', compact('exam', 'session'));
    }

    public function start(Exam $exam)
    {
        $user = auth()->user();
        
        if ($exam->classroom_id !== $user->classroom_id || !$exam->is_active || now() < $exam->start_time || now() > $exam->end_time) {
            abort(403, 'Akses ditolak.');
        }

        if ($exam->questions()->count() === 0) {
            return redirect()->route('student.dashboard')->with('error', 'Ujian ini belum memiliki soal. Hubungi pengawas.');
        }

        $session = ExamSession::firstOrCreate(
            ['user_id' => $user->id, 'exam_id' => $exam->id],
            ['started_at' => now()]
        );

        return redirect()->route('student.exams.attempt', $exam);
    }

    public function attempt(Exam $exam)
    {
        $user = auth()->user();

        // Bug Fix #3: Ensure student belongs to exam's classroom
        if ($exam->classroom_id !== $user->classroom_id) {
            abort(403, 'Anda tidak berhak mengakses ujian ini.');
        }

        $session = ExamSession::where('user_id', $user->id)->where('exam_id', $exam->id)->firstOrFail();

        if ($session->finished_at) {
            return redirect()->route('student.dashboard')->with('error', 'Ujian telah selesai.');
        }

        // Calculate remaining time
        $endTimeBasedOnDuration = $session->started_at->addMinutes($exam->duration_minutes);
        $absoluteEndTime = $exam->end_time;
        $endTime = $endTimeBasedOnDuration < $absoluteEndTime ? $endTimeBasedOnDuration : $absoluteEndTime;

        if (now() >= $endTime) {
            return $this->autoSubmit($session, $exam);
        }

        // Load questions: from module if exists, else directly from exam
        if ($exam->module_id) {
            $questions = \App\Models\Question::where('module_id', $exam->module_id)->with('options')->get();
        } else {
            $exam->load(['questions.options']);
            $questions = $exam->questions;
        }

        $existingAnswers = Answer::where('exam_session_id', $session->id)->pluck('option_id', 'question_id')->toArray();

        return view('student.exams.attempt', compact('exam', 'session', 'endTime', 'existingAnswers', 'questions'));
    }

    public function submit(Request $request, Exam $exam)
    {
        $user = auth()->user();
        $session = ExamSession::where('user_id', $user->id)->where('exam_id', $exam->id)->firstOrFail();
        
        if ($session->finished_at) {
            return redirect()->route('student.dashboard');
        }

        return $this->processSubmission($request, $session, $exam);
    }

    private function processSubmission(Request $request, ExamSession $session, Exam $exam)
    {
        $answers = $request->input('answers', []);

        $correctCount = 0;

        // Use module questions if available
        if ($exam->module_id) {
            $questions = \App\Models\Question::where('module_id', $exam->module_id)->with('options')->get();
        } else {
            $exam->load('questions.options');
            $questions = $exam->questions;
        }

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

        if ($exam->module_id) {
            $totalQuestions = \App\Models\Question::where('module_id', $exam->module_id)->count();
        } else {
            $totalQuestions = $exam->questions()->count();
        }

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
