<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Answer;
use Illuminate\Console\Command;

class E2eCleanup extends Command
{
    protected $signature = 'e2e:cleanup {title=E2E Offline Test} {--exam-only}';
    protected $description = 'Cleanup E2E test data';

    public function handle()
    {
        $title = $this->argument('title');
        $exam = Exam::where('title', $title)->first();
        if (!$exam) {
            $this->line('not_found');
            return;
        }

        Answer::whereIn('exam_session_id', function ($q) use ($exam) {
            $q->select('id')->from('exam_sessions')->where('exam_id', $exam->id);
        })->delete();

        if (!$this->option('exam-only')) {
            ExamSession::where('exam_id', $exam->id)->delete();
            $exam->delete();
        }

        $this->line('cleaned');
    }
}
