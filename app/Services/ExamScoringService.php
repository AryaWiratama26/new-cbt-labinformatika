<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamSession;

class ExamScoringService
{
    /**
     * Hitung ulang nilai sesi. Soal pilihan ganda bernilai 1 bila benar,
     * soal koding bernilai score/100 dan dihitung "pending" sampai dinilai admin.
     *
     * @return array{score: float, pending: int}
     */
    public function recompute(ExamSession $session, Exam $exam): array
    {
        $questions = $exam->getQuestions();
        $total = $questions->count();

        $answers = Answer::where('exam_session_id', $session->id)
            ->with('option')
            ->get()
            ->keyBy('question_id');

        $earned = 0.0;
        $pending = 0;

        foreach ($questions as $question) {
            $answer = $answers->get($question->id);

            if ($question->isCoding()) {
                if (! $answer || $answer->answer_text === null || trim((string) $answer->answer_text) === '') {
                    continue;
                }

                if ($answer->grade_status === Answer::GRADE_GRADED && $answer->score !== null) {
                    $earned += ((float) $answer->score) / 100;
                } else {
                    $pending++;
                }

                continue;
            }

            if ($answer && $answer->option && $answer->option->is_correct) {
                $earned += 1;
            }
        }

        return [
            'score' => $total > 0 ? round(($earned / $total) * 100, 2) : 0,
            'pending' => $pending,
        ];
    }

    public function refreshSession(ExamSession $session, Exam $exam): array
    {
        $result = $this->recompute($session, $exam);

        $session->update([
            'score' => $result['score'],
            'graded_at' => $result['pending'] > 0 ? null : now(),
        ]);

        return $result;
    }
}
