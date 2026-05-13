<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

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

    public function show(Request $request, Course $course, Module $module)
    {
        $module->load('course');

        $query = $module->questions()->with('options');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('content', 'like', "%{$search}%");
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $questions = $query->orderBy('id')->paginate(15)->withQueryString();

        return view('admin.modules.show', compact('course', 'module', 'questions'));
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
                if (!is_dir($extractPath)) {
                    mkdir($extractPath, 0755, true);
                }
                // Resolve sekali saja — digunakan sebagai base path acuan
                $realExtractPath = realpath($extractPath);
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);

                    // BUG #4 fix: ambil basename saja, strip semua subdirektori
                    
                    $basename = basename($filename);
                    if (!$basename || $basename === '.' || $basename === '..') {
                        continue;
                    }

                    // Validasi ekstensi
                    $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions)) {
                        continue;
                    }

                    // Destination: flat di dalam $realExtractPath, tanpa subdirektori
                    $destination = $realExtractPath . DIRECTORY_SEPARATOR . $basename;

                    // Double-check: pastikan destination masih di dalam folder target
                    // Tambahkan trailing slash di base agar "questions2/" tidak dianggap valid
                    if (strpos($destination, $realExtractPath . DIRECTORY_SEPARATOR) !== 0) {
                        continue;
                    }

                    $imageData = $zip->getFromIndex($i);
                    if ($imageData !== false) {
                        file_put_contents($destination, $imageData);
                    }
                }
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
        if ($header === null || empty(array_filter($header))) {
            return redirect()->route('admin.courses.modules.show', [$course, $module])
                ->with('error', 'File CSV kosong atau format tidak valid.');
        }

        $importedCount = 0;
        // BUG #20 fix: Gunakan array $header untuk deteksi kolom, jangan $data[0] karena bisa kosong
        $isNewFormat = count($header) >= 8;

        foreach ($data as $row) {
            if (!is_array($row) || count($row) < 6) continue;

            $pertanyaan = trim($row[0] ?? '');
            if (!$pertanyaan) continue;

            if ($isNewFormat) {
                $gambar  = trim($row[1] ?? '');
                $opsiA   = trim($row[2] ?? '');
                $opsiB   = trim($row[3] ?? '');
                $opsiC   = trim($row[4] ?? '');
                $opsiD   = trim($row[5] ?? '');
                $kunci   = strtoupper(trim($row[6] ?? 'A'));
                $kategori = trim($row[7] ?? '');
            } else {
                $gambar  = '';
                $opsiA   = trim($row[1] ?? '');
                $opsiB   = trim($row[2] ?? '');
                $opsiC   = trim($row[3] ?? '');
                $opsiD   = trim($row[4] ?? '');
                $kunci   = strtoupper(trim($row[5] ?? 'A'));
                $kategori = '';
            }

            if (!in_array($kunci, ['A', 'B', 'C', 'D'])) {
                $kunci = 'A';
            }

            $question = $module->questions()->create([
                'content'  => $pertanyaan,
                'image'    => $gambar ? 'questions/' . $gambar : null,
                'category' => $kategori ?: null,
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
        } elseif ($request->boolean('remove_image') && $question->image) {
            $oldPath = ltrim($question->image, '/');
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            $data['image'] = null;
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

        // BUG #19 fix: salin file gambar ke path baru agar tidak shared dengan soal asli.
        // Jika soal asli dihapus dan gambar ikut dihapus, soal duplikat tidak ikut kehilangan gambar.
        $newImagePath = null;
        if ($question->image) {
            $sourcePath = storage_path('app/public/' . $question->image);
            if (file_exists($sourcePath)) {
                $ext      = pathinfo($question->image, PATHINFO_EXTENSION);
                $newName  = 'questions/' . uniqid('dup_') . '.' . $ext;
                $destPath = storage_path('app/public/' . $newName);
                if (@copy($sourcePath, $destPath)) {
                    $newImagePath = $newName;
                } else {
                    // Fallback: kalau copy gagal (e.g. permission), tetap pakai path lama
                    $newImagePath = $question->image;
                }
            }
        }

        $newQuestion = $module->questions()->create([
            'content'     => $question->content . ' (copy)',
            'image'       => $newImagePath,
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

    public function downloadDocxTemplate()
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addTitle('Template Soal CBT', 1);
        $section->addTextBreak();

        $section->addText(
            'Ikuti format di bawah ini. Simpan gambar di dalam dokumen (Insert > Pictures). '
            . 'Gunakan format penomoran "1.", "2." dst untuk setiap soal.',
            ['italic' => true, 'size' => 10]
        );
        $section->addTextBreak();

        // Soal 1
        $section->addText('1. Siapa presiden pertama Indonesia?', ['bold' => true, 'size' => 11]);
        $section->addText('A. Soekarno');
        $section->addText('B. Soeharto');
        $section->addText('C. B.J. Habibie');
        $section->addText('D. Megawati');
        $section->addText('Kunci: A');
        $section->addText('Kategori: Mudah');
        $section->addText('Pembahasan: Soekarno adalah presiden pertama Republik Indonesia.');
        $section->addTextBreak();

        // Soal 2
        $section->addText('2. Berapakah hasil dari 25 + 17?', ['bold' => true, 'size' => 11]);
        $section->addText('A. 32');
        $section->addText('B. 42');
        $section->addText('C. 52');
        $section->addText('D. 62');
        $section->addText('Kunci: B');
        $section->addText('Kategori: Mudah');
        $section->addText('Pembahasan: 25 + 17 = 42.');
        $section->addTextBreak();

        // Soal 3 dengan gambar
        $section->addText('3. [Gambar bisa disisipkan di sini] Perhatikan gambar di atas. Alat tersebut digunakan untuk...', ['bold' => true, 'size' => 11]);
        $section->addText('A. Mengukur panjang');
        $section->addText('B. Menimbang massa');
        $section->addText('C. Mengukur suhu');
        $section->addText('D. Mengukur volume');
        $section->addText('Kunci: C');
        $section->addText('Kategori: Sedang');
        $section->addText('Pembahasan: Alat pada gambar adalah termometer.');
        $section->addTextBreak();

        // Soal 4 tanpa Kategori & Pembahasan (opsional)
        $section->addText('4. Ibukota Indonesia adalah...', ['bold' => true, 'size' => 11]);
        $section->addText('A. Jakarta');
        $section->addText('B. Surabaya');
        $section->addText('C. Bandung');
        $section->addText('D. Yogyakarta');
        $section->addText('Kunci: A');
        $section->addTextBreak();

        $section->addText(
            'Keterangan:',
            ['bold' => true, 'underline' => 'single']
        );
        $section->addText('- "Kunci:" diisi dengan huruf A, B, C, atau D.');
        $section->addText('- "Kategori:" bisa Mudah, Sedang, atau Sulit (opsional — boleh dihapus).');
        $section->addText('- "Pembahasan:" diisi penjelasan jawaban (opsional — boleh dihapus).');
        $section->addText('- Lihat Soal 4 sebagai contoh format minimal (tanpa Kategori & Pembahasan).');
        $section->addText('- Gambar bisa disisipkan langsung di dalam soal.');

        $filename = 'template_soal.docx';
        $tempPath = tempnam(sys_get_temp_dir(), $filename);
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function importQuestionsDocx(Request $request, Course $course, Module $module)
    {
        $request->validate([
            'docx_file' => 'required|file|mimes:docx|max:10240',
        ]);

        $zip = new \ZipArchive;
        $filePath = $request->file('docx_file')->getRealPath();

        if ($zip->open($filePath) !== true) {
            return redirect()->route('admin.courses.modules.show', [$course, $module])
                ->with('error', 'Gagal membuka file .docx.');
        }

        // Baca relasi untuk mapping rId ke nama file gambar
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $rels = [];
        if ($relsXml !== false) {
            $relsDom = new \DOMDocument();
            $relsDom->loadXML($relsXml);
            foreach ($relsDom->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/relationships', 'Relationship') as $rel) {
                $id = $rel->getAttribute('Id');
                $target = $rel->getAttribute('Target');
                $type = $rel->getAttribute('Type');
                if (str_contains($type, 'image')) {
                    $rels[$id] = $target;
                }
            }
        }

        // Parse document.xml
        $docXml = $zip->getFromName('word/document.xml');
        if ($docXml === false) {
            $zip->close();
            return redirect()->route('admin.courses.modules.show', [$course, $module])
                ->with('error', 'File .docx tidak valid.');
        }

        $dom = new \DOMDocument();
        $dom->loadXML($docXml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $xpath->registerNamespace('pic', 'http://schemas.openxmlformats.org/drawingml/2006/picture');

        // Extract images from ZIP
        $extractPath = storage_path('app/public/questions');
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $imageMap = []; // urutan gambar di dokumen → path file
        $imageIndex = 0;

        // Iterate all paragraphs
        $paragraphs = $xpath->query('//w:p');
        $elements = [];

        foreach ($paragraphs as $p) {
            $text = '';
            $hasImage = false;
            $currentRel = null;

            // Cari teks
            foreach ($xpath->query('.//w:t', $p) as $t) {
                $text .= $t->nodeValue;
            }

            // Cari gambar
            $drawings = $xpath->query('.//w:drawing', $p);
            if ($drawings->length > 0) {
                $hasImage = true;
                $blip = $xpath->query('.//a:blip', $drawings->item(0));
                if ($blip->length > 0) {
                    $embed = $blip->item(0)->getAttribute('r:embed');
                    if ($embed && isset($rels[$embed])) {
                        $currentRel = $rels[$embed];
                        // Extract image file
                        $relPath = $rels[$embed];
                        $sourcePath = 'word/' . $relPath;
                        $imageData = $zip->getFromName($sourcePath);
                        if ($imageData !== false) {
                            $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                $ext = 'png';
                            }
                            $filename = uniqid('docx_') . '.' . $ext;
                            file_put_contents($extractPath . '/' . $filename, $imageData);
                            $currentRel = 'questions/' . $filename;
                        }
                    }
                }
            }

            $elements[] = [
                'text'      => trim($text),
                'has_image' => $hasImage,
                'image'     => $currentRel,
            ];
        }

        $zip->close();

        // Parse elements into questions
        $questions = [];
        $current = null;

        foreach ($elements as $el) {
            $text = $el['text'];

            // Deteksi header soal: "1.", "1)", "1."
            if (preg_match('/^(\d+)[.)]\s*(.+)/', $text, $m)) {
                if ($current && !empty($current['question'])) {
                    $questions[] = $current;
                }
                $current = [
                    'question'    => trim($m[2]),
                    'options'     => [],
                    'key'         => '',
                    'category'    => '',
                    'explanation' => '',
                    'image'       => $el['has_image'] ? $el['image'] : null, // BUG #23 fix: capture inline image on header
                ];
                continue;
            }

            if ($current === null) continue;

            // BUG #23 fix: Deteksi gambar inline (teks ada + gambar ada) atau standalone
            if ($el['has_image'] && $current['image'] === null) {
                $current['image'] = $el['image'];
            }

            // Jika hanya gambar tanpa teks, langsung continue agar tidak nambah newline kosong
            if ($el['has_image'] && empty($text)) {
                continue;
            }

            // Deteksi opsi: "A. ...", "B. ..."
            if (preg_match('/^([A-Da-d])[.)]\s*(.+)/', $text, $m)) {
                $current['options'][strtoupper($m[1])] = trim($m[2]);
                continue;
            }

            // Deteksi kunci: "Kunci: A", "Jawaban: B"
            if (preg_match('/^(Kunci|Jawaban)\s*:\s*([A-Da-d])/i', $text, $m)) {
                $current['key'] = strtoupper($m[2]);
                continue;
            }

            if (preg_match('/^Kategori\s*:\s*(.*)/i', $text, $m)) {
                $current['category'] = trim($m[1]);
                continue;
            }

            if (preg_match('/^Pembahasan\s*:\s*(.*)/i', $text, $m)) {
                $current['explanation'] = trim($m[1]);
                continue;
            }

            // Kalau tidak cocok pola apapun, anggap sebagai lanjutan teks soal
            if (!empty($text)) {
                $current['question'] .= "\n" . $text;
            }
        }

        if ($current && !empty($current['question'])) {
            $questions[] = $current;
        }

        if (empty($questions)) {
            return redirect()->route('admin.courses.modules.show', [$course, $module])
                ->with('error', 'Tidak ditemukan soal dalam format yang benar. Gunakan template yang disediakan.');
        }

        // Mapping kategori
        $categoryMap = [
            'mudah' => 'mudah', 'easy' => 'mudah', 'medium' => 'sedang',
            'sedang' => 'sedang', 'sulit' => 'sulit', 'hard' => 'sulit', 'susah' => 'sulit',
        ];

        $importedCount = 0;
        foreach ($questions as $q) {
            $cat = strtolower(trim($q['category']));
            $category = isset($categoryMap[$cat]) ? $categoryMap[$cat] : null;

            $question = $module->questions()->create([
                'content'     => $q['question'],
                'image'       => $q['image'],
                'category'    => $category,
                'explanation' => $q['explanation'] ?: null,
            ]);

            $keyOrder = ['A', 'B', 'C', 'D'];
            foreach ($keyOrder as $letter) {
                if (isset($q['options'][$letter]) && !empty(trim($q['options'][$letter]))) {
                    $question->options()->create([
                        'content'    => trim($q['options'][$letter]),
                        'is_correct' => $letter === $q['key'],
                    ]);
                }
            }
            $importedCount++;
        }

        return redirect()->route('admin.courses.modules.show', [$course, $module])
            ->with('success', "$importedCount soal berhasil diimpor dari file .docx.");
    }
}
