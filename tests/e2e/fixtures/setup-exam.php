<?php
$now = now();
$examTitle = $argv[1] ?? 'E2E Test Exam';

\App\Models\Exam::where('title', $examTitle)->delete();

$exam = new \App\Models\Exam();
$exam->title = $examTitle;
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
echo 'exam_created:' . $exam->id . "\n";

$user = \App\Models\User::where('username', '20241001')->first();
$session = new \App\Models\ExamSession();
$session->user_id = $user->id;
$session->exam_id = $exam->id;
$session->attempt_number = 1;
$session->started_at = $now;
$session->save();
echo 'session_created:' . $session->id . "\n";
