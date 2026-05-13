<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\User;
use App\Models\Answer;
use Illuminate\Console\Command;

class E2eSetup extends Command
{
    protected $signature = 'e2e:setup-exam {title=E2E Offline Test}';
    protected $description = 'Setup exam data for E2E testing';

    public function handle()
    {
        $title = $this->argument('title');
        $now = now();

        Exam::where('title', $title)->delete();

        $exam = new Exam();
        $exam->title = $title;
        $exam->course_id = 1;
        $exam->module_id = 1;
        $exam->classroom_id = 1;
        $exam->start_time = $now->copy()->subHour();
        $exam->end_time = $now->copy()->addHours(3);
        $exam->duration_minutes = 120;
        $exam->is_active = true;
        $exam->passing_grade = 70;
        $exam->max_attempts = 10;
        $exam->max_tab_switches = 5;
        $exam->require_fullscreen = false;
        $exam->save();

        $user = User::where('username', '20241001')->firstOrFail();
        $session = new ExamSession();
        $session->user_id = $user->id;
        $session->exam_id = $exam->id;
        $session->attempt_number = 1;
        $session->started_at = $now;
        $session->save();

        $this->line('exam_created:' . $exam->id);
        $this->line('session_created:' . $session->id);
    }
}
