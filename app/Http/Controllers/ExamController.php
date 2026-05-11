<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Course;
use App\Models\Classroom;
use App\Models\User;
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
        ]);

        // Auto-fill course_id from the selected module
        $module = \App\Models\Module::findOrFail($validated['module_id']);
        $validated['course_id'] = $module->course_id;
        $validated['is_active'] = $request->has('is_active');

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
        ]);

        $module = \App\Models\Module::findOrFail($validated['module_id']);
        $validated['course_id'] = $module->course_id;
        $validated['is_active'] = $request->has('is_active');

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

        $sessions = ExamSession::where('exam_id', $exam->id)
            ->whereIn('user_id', $students->pluck('id'))
            ->orderBy('attempt_number', 'desc')
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
            $participantsJson = $participants->mapWithKeys(function ($p) {
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
                    $scoreClass = $p->score >= 70 ? 'text-green-600' : 'text-red-500';
                }

                return [$p->username => [
                    'status'      => $p->status,
                    'status_badge' => $statusBadge,
                    'started_at'  => $p->started_at ? $p->started_at->format('H:i:s') : '-',
                    'finished_at' => $p->finished_at ? $p->finished_at->format('H:i:s') : '-',
                    'score'       => $p->score !== null ? (string) $p->score : '-',
                    'score_class' => $scoreClass,
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
}
