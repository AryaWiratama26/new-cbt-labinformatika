<?php
$now = now();
$examTitle = $argv[1] ?? 'E2E Test Exam';

\App\Models\Exam::where('title', $examTitle)->delete();

$exam = new \App\Models\Exam();
$exam->title = $examTitle;
$exam->course_id = 1;
$exam->module_id = 1;
$exam->is_active = true;
$exam->passing_grade = 70;
$exam->max_attempts = 10;
$exam->max_tab_switches = 5;
$exam->require_fullscreen = false;
$exam->save();

$exam->classrooms()->attach(1, [
    'start_time' => $now->copy()->subHour(),
    'end_time' => $now->copy()->addHours(3),
    'duration_minutes' => 120,
]);

$user = \App\Models\User::where('username', '20241001')->first();
$session = new \App\Models\ExamSession();
$session->user_id = $user->id;
$session->exam_id = $exam->id;
$session->attempt_number = 1;
$session->started_at = $now;
$session->save();
echo 'exam_created:' . $exam->id . "\n";
echo 'session_created:' . $session->id . "\n";
