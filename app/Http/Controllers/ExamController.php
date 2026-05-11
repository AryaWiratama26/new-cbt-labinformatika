<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Course;
use App\Models\Classroom;
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

        // Load questions from module if attached, else from exam directly
        if ($exam->module_id) {
            $questions = \App\Models\Question::where('module_id', $exam->module_id)->with('options')->get();
        } else {
            $exam->load('questions.options');
            $questions = $exam->getRelation('questions');
        }

        return view('admin.exams.show', compact('exam', 'questions'));
    }

    public function results(Exam $exam)
    {
        $exam->load('course', 'classroom');
        $sessions = \App\Models\ExamSession::where('exam_id', $exam->id)
            ->with('user')
            ->orderByDesc('score')
            ->get();
            
        return view('admin.exams.results', compact('exam', 'sessions'));
    }
}
