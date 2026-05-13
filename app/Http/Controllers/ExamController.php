<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Course;
use App\Models\Classroom;
use App\Models\User;
use App\Models\Answer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index()
    {
        $exams = Exam::with(['course', 'classroom'])->latest()->get();
        return view('admin.exams.index', compact('exams'));
    }

    public function create()
    {
        $modules = \App\Models\Module::with('course')->withCount('questions')->orderBy('course_id')->get();
        $classrooms = Classroom::orderBy('name')->get();
        return view('admin.exams.create', compact('modules', 'classrooms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'module_id'        => 'required|exists:modules,id',
            'classroom_id'     => 'required|exists:classrooms,id',
            'start_time'       => 'required|date',
            'end_time'         => 'required|date|after:start_time',
            'duration_minutes' => 'required|integer|min:1',
            'is_active'        => 'boolean',
            'passing_grade'    => 'required|integer|min:0|max:100',
            'max_attempts'     => 'required|integer|min:1|max:10',
            'max_tab_switches' => 'nullable|integer|min:1|max:99',
            'require_fullscreen' => 'boolean',
        ]);

        // Auto-fill course_id from the selected module
        $module = \App\Models\Module::findOrFail($validated['module_id']);
        $validated['course_id'] = $module->course_id;
        $validated['is_active'] = $request->boolean('is_active');

        Exam::create($validated);
        return redirect()->route('admin.exams.index')->with('success', 'Jadwal ujian berhasil dibuat.');
    }

    public function edit(Exam $exam)
    {
        $modules = \App\Models\Module::with('course')->withCount('questions')->orderBy('course_id')->get();
        $classrooms = Classroom::orderBy('name')->get();
        return view('admin.exams.edit', compact('exam', 'modules', 'classrooms'));
    }

    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'module_id'        => 'required|exists:modules,id',
            'classroom_id'     => 'required|exists:classrooms,id',
            'start_time'       => 'required|date',
            'end_time'         => 'required|date|after:start_time',
            'duration_minutes' => 'required|integer|min:1',
            'is_active'        => 'boolean',
            'passing_grade'    => 'required|integer|min:0|max:100',
            'max_attempts'     => 'required|integer|min:1|max:10',
            'max_tab_switches' => 'nullable|integer|min:1|max:99',
            'require_fullscreen' => 'boolean',
        ]);

        $module = \App\Models\Module::findOrFail($validated['module_id']);
        $validated['course_id'] = $module->course_id;
        $validated['is_active'] = $request->boolean('is_active');

        $exam->update($validated);
        return redirect()->route('admin.exams.index')->with('success', 'Jadwal ujian berhasil diperbarui.');
    }

    public function destroy(Exam $exam)
    {
        $exam->delete();
        return redirect()->route('admin.exams.index')->with('success', 'Ujian berhasil dihapus.');
    }

    public function show(Exam $exam)
    {
        $exam->load('course', 'classroom', 'module');

        $questions = $exam->getQuestions();

        return view('admin.exams.show', compact('exam', 'questions'));
    }

    public function results(Exam $exam)
    {
        $exam->load('course', 'classroom');

        $allSessions = ExamSession::where('exam_id', $exam->id)
            ->with('user')
            ->orderBy('user_id')
            ->orderBy('attempt_number')
            ->get();

        $students = $allSessions->groupBy('user_id');

        return view('admin.exams.results', compact('exam', 'students'));
    }

    public function monitor(Request $request, Exam $exam)
    {
        $exam->load('course', 'classroom');

        $students = User::where('role', 'mahasiswa')
            ->where('classroom_id', $exam->classroom_id)
            ->orderBy('name')
            ->get();

        // BUG #13 fix: 
        $sessions = ExamSession::where('exam_id', $exam->id)
            ->whereIn('user_id', $students->pluck('id'))
            ->orderBy('attempt_number', 'asc')
            ->get()
            ->keyBy('user_id');

        $participants = $students->map(function ($student) use ($sessions) {
            $session = $sessions->get($student->id);

            if (!$session) {
                $student->status = 'not_started';
                $student->started_at = null;
                $student->finished_at = null;
                $student->score = null;
            } elseif ($session->finished_at) {
                $student->status = 'finished';
                $student->started_at = $session->started_at;
                $student->finished_at = $session->finished_at;
                $student->score = $session->score;
            } else {
                $student->status = 'in_progress';
                $student->started_at = $session->started_at;
                $student->finished_at = null;
                $student->score = null;
            }

            return $student;
        });

        $stats = [
            'total'       => $participants->count(),
            'not_started' => $participants->where('status', 'not_started')->count(),
            'in_progress' => $participants->where('status', 'in_progress')->count(),
            'finished'    => $participants->where('status', 'finished')->count(),
        ];

        if ($request->ajax() || $request->wantsJson()) {
            // BUG #6 fix: gunakan $exam->passing_grade, bukan hardcode 70
            $passingGrade = $exam->passing_grade;

            $participantsJson = $participants->mapWithKeys(function ($p) use ($passingGrade) {
                $statusBadge = '';
                if ($p->status === 'in_progress') {
                    $statusBadge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full border border-green-200"><span class="h-2 w-2 rounded-full bg-green-500 inline-block animate-pulse"></span> Sedang Ujian</span>';
                } elseif ($p->status === 'finished') {
                    $statusBadge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-full border border-blue-200"><i class="ph ph-check-circle text-sm"></i> Selesai</span>';
                } else {
                    $statusBadge = '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full border border-gray-200"><i class="ph ph-clock text-sm"></i> Belum Mulai</span>';
                }

                $scoreClass = 'text-gray-400';
                if ($p->score !== null) {
                    $scoreClass = $p->score >= $passingGrade ? 'text-green-600' : 'text-red-500';
                }

                return [$p->username => [
                    'status'       => $p->status,
                    'status_badge' => $statusBadge,
                    'started_at'   => $p->started_at ? $p->started_at->format('H:i:s') : '-',
                    'finished_at'  => $p->finished_at ? $p->finished_at->format('H:i:s') : '-',
                    'score'        => $p->score !== null ? (string) $p->score : '-',
                    'score_class'  => $scoreClass,
                ]];
            });

            return response()->json([
                'participants' => $participantsJson,
                'stats'        => $stats,
                'time'         => now()->format('H:i:s'),
            ]);
        }

        return view('admin.exams.monitor', compact('exam', 'participants', 'stats'));
    }

    public function exportPdf(Exam $exam)
    {
        $exam->load('course', 'classroom');

        $allSessions = ExamSession::where('exam_id', $exam->id)
            ->with('user')
            ->orderBy('user_id')
            ->orderBy('attempt_number')
            ->get();

        $students = $allSessions->groupBy('user_id');

        $finishedStudents = $students->filter(fn($sessions) => $sessions->where('finished_at', '!=', null)->isNotEmpty());
        $totalStudents = $finishedStudents->count();
        $lastScores = $finishedStudents->map(fn($sessions) => $sessions->where('finished_at', '!=', null)->last()->score ?? 0);
        $avgScore = $lastScores->count() > 0 ? round($lastScores->avg(), 1) : 0;
        $passed = $lastScores->filter(fn($s) => $s >= $exam->passing_grade)->count();
        $failed = $totalStudents - $passed;
        $highest = $lastScores->max() ?? 0;
        $lowest = $lastScores->min() ?? 0;

        $pdf = Pdf::loadView('admin.exports.exam-pdf', compact(
            'exam', 'students', 'avgScore', 'passed', 'failed', 'totalStudents', 'highest', 'lowest'
        ));

        $filename = 'Laporan_Nilai_' . str_replace('/', '-', $exam->title) . '.pdf';
        return $pdf->download($filename);
    }

    public function resultsCsv(Exam $exam)
    {
        $exam->load('course', 'classroom');

        $allSessions = ExamSession::where('exam_id', $exam->id)
            ->with('user')
            ->orderBy('user_id')
            ->orderBy('attempt_number')
            ->get();

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=laporan_nilai_{$exam->id}.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0",
        ];

        $callback = function () use ($exam, $allSessions) {
            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['No', 'NIM', 'Nama Mahasiswa', 'Percobaan', 'Waktu Submit', 'Skor', 'Status']);

            $students = $allSessions->groupBy('user_id');
            $no = 1;

            $sanitize = function ($value) {
                return (is_string($value) && preg_match('/^[=\-+\@]/', $value)) ? "'" . $value : $value;
            };

            foreach ($students as $userId => $sessions) {
                foreach ($sessions as $session) {
                    $status = $session->score !== null
                        ? ($session->score >= $exam->passing_grade ? 'LULUS' : 'GAGAL')
                        : '-';
                    $waktu = $session->finished_at
                        ? $session->finished_at->format('Y-m-d H:i:s')
                        : 'Belum Selesai';

                    fputcsv($file, array_map($sanitize, [
                        $no,
                        $session->user->username,
                        $session->user->name,
                        $session->attempt_number,
                        $waktu,
                        $session->score ?? '-',
                        $status,
                    ]));
                }
                $no++;
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function studentReport(Exam $exam, User $user)
    {
        $exam->load('course', 'classroom');

        $questions = $exam->getQuestions();

        $sessions = ExamSession::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->orderBy('attempt_number')
            ->with('answers.question.options', 'answers.option')
            ->get();

        if ($sessions->isEmpty()) {
            return redirect()->route('admin.exams.results', $exam)
                ->with('error', 'Mahasiswa belum mengerjakan ujian ini.');
        }

        return view('admin.exams.student-report', compact('exam', 'user', 'sessions', 'questions'));
    }
}
