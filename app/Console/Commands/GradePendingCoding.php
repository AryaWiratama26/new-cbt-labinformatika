<?php

namespace App\Console\Commands;

use App\Models\Answer;
use App\Models\Question;
use App\Services\AiCodeGrader;
use App\Services\ExamScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GradePendingCoding extends Command
{
    protected $signature = 'grade:coding-pending {--limit=10 : Maksimal jawaban yang diproses sekali jalan}';

    protected $description = 'Nilai jawaban koding yang masih pending memakai AI (dijalankan via cron tiap menit)';

    public function handle(AiCodeGrader $grader, ExamScoringService $scoring): int
    {
        $lock = Cache::lock('grade:coding-pending', 55);

        if (! $lock->get()) {
            $this->line('already_running');

            return self::SUCCESS;
        }

        try {
            return $this->runGrading($grader, $scoring);
        } finally {
            $lock->release();
        }
    }

    private function runGrading(AiCodeGrader $grader, ExamScoringService $scoring): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $answers = Answer::where('grade_status', Answer::GRADE_PENDING)
            ->whereNotNull('answer_text')
            ->whereRaw("TRIM(answer_text) <> ''")
            ->where(function ($q) {
                $q->whereNull('graded_by')->orWhere('graded_by', '<>', Answer::GRADED_BY_MANUAL);
            })
            ->whereHas('session', fn ($q) => $q->whereNotNull('finished_at'))
            ->whereHas('question', fn ($q) => $q->where('type', Question::TYPE_CODING))
            ->with(['question', 'session.exam'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($answers->isEmpty()) {
            $this->line('nothing_pending');

            return self::SUCCESS;
        }

        $graded = 0;
        $skipped = 0;
        $touchedSessions = [];

        foreach ($answers as $answer) {
            try {
                $result = $grader->grade($answer->question, (string) $answer->answer_text);
            } catch (\Throwable $e) {
                Log::error('GradePendingCoding: grader exception, dilewati.', [
                    'answer_id' => $answer->id,
                    'message' => $e->getMessage(),
                ]);
                $skipped++;

                continue;
            }

            if ($result['score'] === null) {
                if (in_array($result['error'], ['rate_limit', 'unauthorized', 'not_configured'], true)) {
                    $this->warn($result['error'].', berhenti sampai cron berikutnya.');
                    break;
                }

                $skipped++;

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

            $touchedSessions[$answer->exam_session_id] = $answer->session;
            $graded++;
            sleep(2);
        }

        foreach ($touchedSessions as $session) {
            if ($session && $session->exam) {
                $scoring->refreshSession($session, $session->exam);
            }
        }

        $this->line("graded={$graded} skipped={$skipped}");

        return self::SUCCESS;
    }
}
