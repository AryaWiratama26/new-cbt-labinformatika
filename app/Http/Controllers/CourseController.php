<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::latest()->get();
        return view('admin.courses.index', compact('courses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:courses,code',
            'name' => 'required|string|max:255',
        ]);

        Course::create($validated);
        return redirect()->route('admin.courses.index')->with('success', 'Mata kuliah berhasil ditambahkan.');
    }

    public function destroy(Course $course)
    {
        // BUG #26 fix: Jangan biarkan admin menghapus course yang masih memiliki modul atau ujian
        if ($course->modules()->exists() || $course->exams()->exists()) {
            return redirect()->route('admin.courses.index')->with('error', 'Gagal dihapus! Mata kuliah ini masih memiliki modul atau ujian terkait.');
        }

        $course->delete();
        return redirect()->route('admin.courses.index')->with('success', 'Mata kuliah berhasil dihapus.');
    }
}
