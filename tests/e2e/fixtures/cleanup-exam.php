<?php
$examTitle = $argv[1] ?? 'E2E Test Exam';
$exam = \App\Models\Exam::where('title', $examTitle)->first();
if (!$exam) { echo "not_found\n"; exit; }

\App\Models\Answer::whereIn('exam_session_id', function($q) use ($exam) {
    $q->select('id')->from('exam_sessions')->where('exam_id', $exam->id);
})->delete();

\App\Models\ExamSession::where('exam_id', $exam->id)->delete();
\App\Models\Exam::where('id', $exam->id)->delete();
echo "cleaned\n";
