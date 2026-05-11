<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ModuleController extends Controller
{
    public function index(Course $course)
    {
        $modules = $course->modules()->withCount('questions')->get();
        return view('admin.modules.index', compact('course', 'modules'));
    }

    public function create(Course $course)
    {
        return view('admin.modules.create', compact('course'));
    }

    public function store(Request $request, Course $course)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'module_number' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $course->modules()->create($request->only('name', 'module_number', 'description'));
        return redirect()->route('admin.courses.modules.index', $course)->with('success', 'Modul berhasil ditambahkan.');
    }

    public function show(Course $course, Module $module)
    {
        $module->load(['questions.options', 'course']);
        return view('admin.modules.show', compact('course', 'module'));
    }

    public function destroy(Course $course, Module $module)
    {
        // Delete all question images
        foreach ($module->questions as $question) {
            if ($question->image) {
                $path = ltrim($question->image, '/');
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }
        $module->delete();
        return redirect()->route('admin.courses.modules.index', $course)->with('success', 'Modul berhasil dihapus.');
    }

    public function importQuestions(Request $request, Course $course, Module $module)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'images_zip' => 'nullable|file|mimes:zip|max:10240',
        ]);

        // Handle Images Zip
        if ($request->hasFile('images_zip')) {
            $zip = new \ZipArchive;
            if ($zip->open($request->file('images_zip')->getRealPath()) === true) {
                $zip->extractTo(storage_path('app/public/questions'));
                $zip->close();
            }
        }

        $file = $request->file('csv_file');
        $data = array_map('str_getcsv', file($file->getRealPath()));
        array_shift($data); // Remove header

        $importedCount = 0;
        foreach ($data as $row) {
            $pertanyaan = trim($row[0] ?? '');
            $gambar     = trim($row[1] ?? '');
            $opsiA      = trim($row[2] ?? '');
            $opsiB      = trim($row[3] ?? '');
            $opsiC      = trim($row[4] ?? '');
            $opsiD      = trim($row[5] ?? '');
            $kunci      = strtoupper(trim($row[6] ?? 'A'));

            if ($pertanyaan) {
                $question = $module->questions()->create([
                    'content' => $pertanyaan,
                    'image'   => $gambar ? 'questions/' . $gambar : null,
                ]);

                $options = ['A' => $opsiA, 'B' => $opsiB, 'C' => $opsiC, 'D' => $opsiD];
                foreach ($options as $key => $text) {
                    if ($text) {
                        $question->options()->create([
                            'content'    => $text,
                            'is_correct' => ($key === $kunci),
                        ]);
                    }
                }
                $importedCount++;
            }
        }

        return redirect()->route('admin.courses.modules.show', [$course, $module])
            ->with('success', "$importedCount soal berhasil diimpor ke modul.");
    }

    public function createQuestion(Course $course, Module $module)
    {
        return view('admin.modules.create_question', compact('course', 'module'));
    }

    public function storeQuestion(Request $request, Course $course, Module $module)
    {
        $request->validate([
            'content'        => 'required|string',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'options'        => 'required|array|min:4',
            'options.*'      => 'required|string',
            'correct_option' => 'required|in:0,1,2,3',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('questions', 'public');
        }

        $question = $module->questions()->create([
            'content' => $request->content,
            'image'   => $imagePath,
        ]);

        foreach ($request->options as $index => $optionContent) {
            $question->options()->create([
                'content'    => $optionContent,
                'is_correct' => ($index == $request->correct_option),
            ]);
        }

        return redirect()->route('admin.courses.modules.show', [$course, $module])
            ->with('success', 'Soal berhasil ditambahkan ke modul.');
    }

    public function destroyQuestion(Course $course, Module $module, Question $question)
    {
        if ($question->image) {
            // Normalize path: strip leading 'storage/' or 'public/' if present
            $path = preg_replace('#^(storage/|public/)#', '', $question->image);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $question->delete();
        return redirect()->route('admin.courses.modules.show', [$course, $module])
            ->with('success', 'Soal berhasil dihapus.');
    }
}
