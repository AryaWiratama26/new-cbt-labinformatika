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

        if ($request->hasFile('images_zip')) {
            $zip = new \ZipArchive;
            if ($zip->open($request->file('images_zip')->getRealPath()) === true) {
                $extractPath = storage_path('app/public/questions');
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    $realPath = realpath($extractPath . '/' . $filename);
                    if ($realPath === false || !str_starts_with($realPath, realpath($extractPath) . '/')) {
                        continue;
                    }
                }
                $zip->extractTo($extractPath);
                $zip->close();
            }
        }

        $file = $request->file('csv_file');
        $contents = file_get_contents($file->getRealPath());
        if (substr($contents, 0, 3) === "\xEF\xBB\xBF") {
            $contents = substr($contents, 3);
        }
        $data = array_map('str_getcsv', explode("\n", $contents));

        $header = array_shift($data);
        if ($header === null) {
            return redirect()->route('admin.courses.modules.show', [$course, $module])
                ->with('error', 'File CSV kosong.');
        }

        $importedCount = 0;
        foreach ($data as $row) {
            if (!is_array($row) || count($row) < 7) continue;

            $pertanyaan = trim($row[0] ?? '');
            $gambar     = trim($row[1] ?? '');
            $opsiA      = trim($row[2] ?? '');
            $opsiB      = trim($row[3] ?? '');
            $opsiC      = trim($row[4] ?? '');
            $opsiD      = trim($row[5] ?? '');
            $kunci      = strtoupper(trim($row[6] ?? 'A'));

            if (!in_array($kunci, ['A', 'B', 'C', 'D'])) {
                $kunci = 'A';
            }

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
            'category'       => 'nullable|in:mudah,sedang,sulit',
            'explanation'    => 'nullable|string',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('questions', 'public');
        }

        $question = $module->questions()->create([
            'content'     => $request->content,
            'image'       => $imagePath,
            'category'    => $request->category,
            'explanation' => $request->explanation,
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

    public function editQuestion(Course $course, Module $module, Question $question)
    {
        $question->load('options');
        return view('admin.modules.edit_question', compact('course', 'module', 'question'));
    }

    public function updateQuestion(Request $request, Course $course, Module $module, Question $question)
    {
        $request->validate([
            'content'        => 'required|string',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'options'        => 'required|array|min:4',
            'options.*'      => 'required|string',
            'correct_option' => 'required|in:0,1,2,3',
            'category'       => 'nullable|in:mudah,sedang,sulit',
            'explanation'    => 'nullable|string',
        ]);

        $data = [
            'content'     => $request->content,
            'category'    => $request->category,
            'explanation' => $request->explanation,
        ];

        if ($request->hasFile('image')) {
            if ($question->image) {
                $oldPath = ltrim($question->image, '/');
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $data['image'] = $request->file('image')->store('questions', 'public');
        }

        $question->update($data);

        $question->options()->delete();
        $correctIdx = (int) $request->correct_option;
        foreach ($request->options as $index => $optionContent) {
            $question->options()->create([
                'content'    => $optionContent,
                'is_correct' => ($index === $correctIdx),
            ]);
        }

        return redirect()->route('admin.courses.modules.show', [$course, $module])
            ->with('success', 'Soal berhasil diperbarui.');
    }

    public function duplicateQuestion(Course $course, Module $module, Question $question)
    {
        $question->load('options');

        $newQuestion = $module->questions()->create([
            'content'     => $question->content . ' (copy)',
            'image'       => $question->image,
            'category'    => $question->category,
            'explanation' => $question->explanation,
        ]);

        foreach ($question->options as $option) {
            $newQuestion->options()->create([
                'content'    => $option->content,
                'is_correct' => $option->is_correct,
            ]);
        }

        return redirect()->route('admin.courses.modules.show', [$course, $module])
            ->with('success', 'Soal berhasil digandakan.');
    }

    public function destroyQuestion(Course $course, Module $module, Question $question)
    {
        if ($question->image) {
            $path = ltrim($question->image, '/');
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $question->delete();
        return redirect()->route('admin.courses.modules.show', [$course, $module])
            ->with('success', 'Soal berhasil dihapus.');
    }
}
