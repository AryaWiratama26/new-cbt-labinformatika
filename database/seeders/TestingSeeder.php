<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Module;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestingSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai TestingSeeder...');

        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name'     => 'Administrator',
                'role'     => 'admin',
                'email'    => 'admin@cbt.test',
                'password' => Hash::make('admin'),
            ]
        );
        $this->command->info("Admin: username=admin, password=admin");

        $classrooms = collect([
            ['name' => 'IF-A', 'academic_year' => '2025/2026', 'semester' => 'Genap'],
            ['name' => 'IF-B', 'academic_year' => '2025/2026', 'semester' => 'Genap'],
            ['name' => 'IF-C', 'academic_year' => '2025/2026', 'semester' => 'Genap'],
        ])->map(fn($c) => Classroom::updateOrCreate(['name' => $c['name']], $c));

        $this->command->info("3 Kelas: IF-A, IF-B, IF-C");

        $nimCounter = 1;
        $allStudents = collect();

        foreach ($classrooms as $classroom) {
            for ($i = 0; $i < 5; $i++) {
                $nim = '31201' . str_pad($nimCounter, 4, '0', STR_PAD_LEFT);
                $student = User::updateOrCreate(
                    ['username' => $nim],
                    [
                        'name'         => $this->fakeName($nimCounter),
                        'role'         => 'mahasiswa',
                        'classroom_id' => $classroom->id,
                        'password'     => Hash::make('password'),
                    ]
                );
                $allStudents->push($student);
                $nimCounter++;
            }
        }
        $this->command->info("15 Mahasiswa (password=password untuk semua)");

        $courses = $this->seedCoursesModulesQuestions();
        $this->command->info("2 Mata Kuliah, 4 Modul, 20 Soal");

        $classroomA = $classrooms[0];
        $classroomB = $classrooms[1];
        $classroomC = $classrooms[2];

        $studentsA = $allStudents->where('classroom_id', $classroomA->id)->values();
        $studentsB = $allStudents->where('classroom_id', $classroomB->id)->values();
        $studentsC = $allStudents->where('classroom_id', $classroomC->id)->values();

        $basisData = $courses['basis_data'];
        $algo      = $courses['algoritma'];

        $examActive = $this->createExamWithClassroom(
            ['title' => '[TEST] Ujian Basis Data - Aktif'],
            [
                'description' => "Ujian ini sedang berlangsung.\nAnda bisa login sebagai mahasiswa IF-A untuk mengerjakan.\n\nPeraturan:\n- Kerjakan dengan jujur\n- Tidak boleh membuka tab lain",
                'course_id'   => $basisData->id,
                'module_id'   => $basisData->modules->first()->id,
                'is_active'   => true,
                'passing_grade' => 70,
                'max_attempts' => 1,
                'max_tab_switches' => 5,
                'require_fullscreen' => false,
            ],
            [$classroomA->id => [
                'start_time' => now()->subHour(),
                'end_time' => now()->addHours(3),
                'duration_minutes' => 60,
            ]]
        );
        $this->command->info("Ujian Aktif: '{$examActive->title}' (IF-A, sekarang)");

        $examFinished = $this->createExamWithClassroom(
            ['title' => '[TEST] Ujian Algoritma - Selesai'],
            [
                'description' => 'Ujian yang sudah selesai kemarin, lengkap dengan skor mahasiswa.',
                'course_id'   => $algo->id,
                'module_id'   => $algo->modules->first()->id,
                'is_active'   => true,
                'passing_grade' => 60,
                'max_attempts' => 1,
                'max_tab_switches' => null,
                'require_fullscreen' => false,
            ],
            [$classroomA->id => [
                'start_time' => now()->subDay()->startOfDay()->addHours(8),
                'end_time' => now()->subDay()->startOfDay()->addHours(10),
                'duration_minutes' => 90,
            ]]
        );
        $this->seedFinishedExam($examFinished, $studentsA);
        $this->command->info("Ujian Selesai: '{$examFinished->title}' (IF-A, kemarin, 5 sesi)");

        $examFuture = $this->createExamWithClassroom(
            ['title' => '[TEST] Ujian Basis Data - Besok'],
            [
                'description' => 'Ujian ini dijadwalkan untuk besok. Mahasiswa belum bisa mengerjakan.',
                'course_id'   => $basisData->id,
                'module_id'   => $basisData->modules->last()->id,
                'is_active'   => true,
                'passing_grade' => 70,
                'max_attempts' => 1,
                'max_tab_switches' => null,
                'require_fullscreen' => false,
            ],
            [$classroomA->id => [
                'start_time' => now()->addDay()->startOfDay()->addHours(9),
                'end_time' => now()->addDay()->startOfDay()->addHours(11),
                'duration_minutes' => 60,
            ]]
        );
        $this->command->info("Ujian Mendatang: '{$examFuture->title}' (IF-A, besok)");

        $examRemedial = $this->createExamWithClassroom(
            ['title' => '[TEST] Ujian Algoritma - Remedial'],
            [
                'description' => "Ujian dengan 3 percobaan. Mahasiswa yang gagal bisa remedial.\nNilai minimal: 75",
                'course_id'   => $algo->id,
                'module_id'   => $algo->modules->last()->id,
                'is_active'   => true,
                'passing_grade' => 75,
                'max_attempts' => 3,
                'max_tab_switches' => null,
                'require_fullscreen' => false,
            ],
            [$classroomB->id => [
                'start_time' => now()->subHours(2),
                'end_time' => now()->addHours(4),
                'duration_minutes' => 45,
            ]]
        );
        $this->seedRemedialExam($examRemedial, $studentsB);
        $this->command->info("Ujian Remedial: '{$examRemedial->title}' (IF-B, 3 percobaan)");

        $examFullscreen = $this->createExamWithClassroom(
            ['title' => '[TEST] Ujian Basis Data - Fullscreen'],
            [
                'description' => "Ujian dengan wajib fullscreen dan deteksi tab.\nMaksimal 3 kali pindah tab.",
                'course_id'   => $basisData->id,
                'module_id'   => $basisData->modules->first()->id,
                'is_active'   => true,
                'passing_grade' => 65,
                'max_attempts' => 1,
                'max_tab_switches' => 3,
                'require_fullscreen' => true,
            ],
            [$classroomC->id => [
                'start_time' => now()->subMinutes(30),
                'end_time' => now()->addHours(2),
                'duration_minutes' => 60,
            ]]
        );
        $this->command->info("Ujian Fullscreen: '{$examFullscreen->title}' (IF-C, wajib fullscreen)");

        $examActiveB = $this->createExamWithClassroom(
            ['title' => '[TEST] Ujian Basis Data - IF-B Aktif'],
            [
                'description' => 'Ujian aktif untuk kelas IF-B.',
                'course_id'   => $basisData->id,
                'module_id'   => $basisData->modules->last()->id,
                'is_active'   => true,
                'passing_grade' => 70,
                'max_attempts' => 1,
                'max_tab_switches' => null,
                'require_fullscreen' => false,
            ],
            [$classroomB->id => [
                'start_time' => now()->subMinutes(45),
                'end_time' => now()->addHours(2),
                'duration_minutes' => 60,
            ]]
        );
        $this->command->info("Ujian Aktif IF-B: '{$examActiveB->title}'");

        $this->command->newLine();
        $this->command->info('TestingSeeder selesai!');
        $this->command->table(
            ['Role', 'Username', 'Password', 'Kelas'],
            [
                ['Admin',     'admin',       'admin',    '-'],
                ['Mahasiswa', '312010001',   'password', 'IF-A'],
                ['Mahasiswa', '312010002',   'password', 'IF-A'],
                ['Mahasiswa', '312010006',   'password', 'IF-B'],
                ['Mahasiswa', '312010011',   'password', 'IF-C'],
            ]
        );
    }

    private function createExamWithClassroom(array $unique, array $data, array $classroomData): Exam
    {
        $exam = Exam::updateOrCreate($unique, $data);
        $exam->classrooms()->sync($classroomData);
        return $exam;
    }

    private function seedCoursesModulesQuestions(): array
    {
        $basisData = Course::updateOrCreate(
            ['code' => 'IF201'],
            ['name' => 'Basis Data']
        );

        $bdModule1 = Module::updateOrCreate(
            ['course_id' => $basisData->id, 'module_number' => 1],
            ['name' => 'DDL & DML', 'description' => 'Data Definition Language dan Data Manipulation Language']
        );
        $bdModule2 = Module::updateOrCreate(
            ['course_id' => $basisData->id, 'module_number' => 2],
            ['name' => 'Normalisasi & Relasi', 'description' => 'Normalisasi database dan hubungan antar tabel']
        );

        $this->seedQuestionsForModule($bdModule1, [
            ['content' => 'Perintah SQL yang digunakan untuk membuat tabel baru adalah...', 'options' => ['CREATE TABLE', 'INSERT TABLE', 'MAKE TABLE', 'NEW TABLE'], 'correct' => 0, 'category' => 'DDL'],
            ['content' => 'Perintah SQL untuk menambahkan data baru ke dalam tabel adalah...', 'options' => ['INSERT INTO', 'ADD INTO', 'PUT INTO', 'CREATE INTO'], 'correct' => 0, 'category' => 'DML'],
            ['content' => 'Apa fungsi dari perintah ALTER TABLE?', 'options' => ['Mengubah struktur tabel', 'Menghapus tabel', 'Menampilkan data tabel', 'Membuat tabel baru'], 'correct' => 0, 'category' => 'DDL'],
            ['content' => 'Perintah untuk menghapus seluruh data dalam tabel tanpa menghapus strukturnya adalah...', 'options' => ['TRUNCATE TABLE', 'DROP TABLE', 'DELETE ALL', 'REMOVE TABLE'], 'correct' => 0, 'category' => 'DDL'],
            ['content' => 'Klausa WHERE pada perintah SELECT berfungsi untuk...', 'options' => ['Menyaring data berdasarkan kondisi', 'Mengurutkan data', 'Mengelompokkan data', 'Menghitung jumlah data'], 'correct' => 0, 'category' => 'DML'],
        ]);

        $this->seedQuestionsForModule($bdModule2, [
            ['content' => 'Normalisasi bertujuan untuk...', 'options' => ['Mengurangi redundansi data', 'Menambah jumlah tabel', 'Mempercepat query', 'Menghapus data duplikat'], 'correct' => 0, 'category' => 'Normalisasi'],
            ['content' => 'Bentuk normal ke-1 (1NF) mensyaratkan...', 'options' => ['Setiap kolom bernilai atomik', 'Tidak ada dependensi parsial', 'Tidak ada dependensi transitif', 'Semua kolom adalah primary key'], 'correct' => 0, 'category' => 'Normalisasi'],
            ['content' => 'Foreign Key digunakan untuk...', 'options' => ['Menghubungkan dua tabel', 'Membuat index', 'Menghapus tabel', 'Menambah kolom'], 'correct' => 0, 'category' => 'Relasi'],
            ['content' => 'Relasi one-to-many berarti...', 'options' => ['Satu record berhubungan dengan banyak record di tabel lain', 'Satu record berhubungan dengan satu record', 'Banyak record berhubungan dengan banyak record', 'Tidak ada hubungan antar tabel'], 'correct' => 0, 'category' => 'Relasi'],
            ['content' => 'Apa itu Primary Key?', 'options' => ['Kolom unik yang mengidentifikasi setiap baris', 'Kolom yang bisa bernilai NULL', 'Kolom yang berisi data duplikat', 'Kolom untuk menghubungkan tabel'], 'correct' => 0, 'category' => 'Relasi'],
        ]);

        $algo = Course::updateOrCreate(
            ['code' => 'IF102'],
            ['name' => 'Algoritma & Pemrograman']
        );

        $algoModule1 = Module::updateOrCreate(
            ['course_id' => $algo->id, 'module_number' => 1],
            ['name' => 'Dasar Pemrograman', 'description' => 'Variabel, tipe data, operator, dan struktur kontrol']
        );
        $algoModule2 = Module::updateOrCreate(
            ['course_id' => $algo->id, 'module_number' => 2],
            ['name' => 'Array & Fungsi', 'description' => 'Penggunaan array dan pembuatan fungsi']
        );

        $this->seedQuestionsForModule($algoModule1, [
            ['content' => 'Tipe data yang digunakan untuk menyimpan bilangan bulat di PHP adalah...', 'options' => ['int', 'float', 'string', 'boolean'], 'correct' => 0, 'category' => 'Tipe Data'],
            ['content' => 'Operator yang digunakan untuk membandingkan dua nilai adalah...', 'options' => ['==', '=', ':=', '->'], 'correct' => 0, 'category' => 'Operator'],
            ['content' => 'Struktur kontrol yang digunakan untuk perulangan adalah...', 'options' => ['for', 'if', 'switch', 'try'], 'correct' => 0, 'category' => 'Kontrol'],
            ['content' => 'Apa output dari echo 5 + 3 * 2?', 'options' => ['11', '16', '13', '10'], 'correct' => 0, 'category' => 'Operator'],
            ['content' => 'Manakah yang merupakan variabel valid di PHP?', 'options' => ['$nama_saya', '123abc', 'nama-saya', '@nama'], 'correct' => 0, 'category' => 'Variabel'],
        ]);

        $this->seedQuestionsForModule($algoModule2, [
            ['content' => 'Array di PHP dimulai dari index...', 'options' => ['0', '1', '-1', 'Bebas'], 'correct' => 0, 'category' => 'Array'],
            ['content' => 'Fungsi count() pada array digunakan untuk...', 'options' => ['Menghitung jumlah elemen', 'Mengurutkan elemen', 'Menghapus elemen', 'Menambah elemen'], 'correct' => 0, 'category' => 'Array'],
            ['content' => 'Keyword untuk membuat fungsi di PHP adalah...', 'options' => ['function', 'def', 'func', 'method'], 'correct' => 0, 'category' => 'Fungsi'],
            ['content' => 'Apa kegunaan keyword "return" dalam fungsi?', 'options' => ['Mengembalikan nilai dari fungsi', 'Menghentikan program', 'Mendeklarasikan variabel', 'Memanggil fungsi lain'], 'correct' => 0, 'category' => 'Fungsi'],
            ['content' => 'Fungsi array_push() digunakan untuk...', 'options' => ['Menambah elemen di akhir array', 'Menghapus elemen terakhir', 'Mengurutkan array', 'Membalik array'], 'correct' => 0, 'category' => 'Array'],
        ]);

        $basisData->load('modules');
        $algo->load('modules');

        return ['basis_data' => $basisData, 'algoritma' => $algo];
    }

    private function seedQuestionsForModule(Module $module, array $questionsData): void
    {
        foreach ($questionsData as $qData) {
            $question = Question::updateOrCreate(
                ['module_id' => $module->id, 'content' => $qData['content']],
                ['category' => $qData['category'] ?? null]
            );

            if ($question->options()->count() === 0) {
                foreach ($qData['options'] as $idx => $optContent) {
                    Option::create([
                        'question_id' => $question->id,
                        'content'     => $optContent,
                        'is_correct'  => ($idx === $qData['correct']),
                    ]);
                }
            }
        }
    }

    private function seedFinishedExam(Exam $exam, $students): void
    {
        $questions = $exam->getQuestions();
        if ($questions->isEmpty()) return;

        $schedule = $exam->getScheduleForClassroom($students->first()->classroom_id);
        if (!$schedule) return;

        $scores = [85.00, 72.50, 55.00, 90.00, 40.00];

        foreach ($students as $i => $student) {
            $session = ExamSession::updateOrCreate(
                ['user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => 1],
                [
                    'started_at'   => $schedule->start_time->copy()->addMinutes(rand(0, 10)),
                    'finished_at'  => $schedule->start_time->copy()->addMinutes(rand(30, 80)),
                    'score'        => $scores[$i % count($scores)],
                    'tab_switches' => rand(0, 2),
                ]
            );

            $this->seedAnswersForSession($session, $questions, $scores[$i % count($scores)] >= 60);
        }
    }

    private function seedRemedialExam(Exam $exam, $students): void
    {
        $questions = $exam->getQuestions();
        if ($questions->isEmpty()) return;

        $this->createFinishedSession($exam, $students[0], 1, 80.00, $questions);
        $this->createFinishedSession($exam, $students[1], 1, 50.00, $questions);
        $this->createFinishedSession($exam, $students[1], 2, 78.00, $questions);
        $this->createFinishedSession($exam, $students[2], 1, 40.00, $questions);
        $this->createFinishedSession($exam, $students[3], 1, 60.00, $questions);
        $this->createFinishedSession($exam, $students[3], 2, 65.00, $questions);
    }

    private function createFinishedSession(Exam $exam, User $student, int $attempt, float $score, $questions): void
    {
        $schedule = $exam->getScheduleForClassroom($student->classroom_id);
        if (!$schedule) return;

        $offset = ($attempt - 1) * 60;
        $session = ExamSession::updateOrCreate(
            ['user_id' => $student->id, 'exam_id' => $exam->id, 'attempt_number' => $attempt],
            [
                'started_at'   => $schedule->start_time->copy()->addMinutes($offset + rand(0, 5)),
                'finished_at'  => $schedule->start_time->copy()->addMinutes($offset + rand(20, 50)),
                'score'        => $score,
                'tab_switches' => rand(0, 3),
            ]
        );

        $this->seedAnswersForSession($session, $questions, $score >= $exam->passing_grade);
    }

    private function seedAnswersForSession(ExamSession $session, $questions, bool $pass): void
    {
        foreach ($questions as $question) {
            $options = $question->options;
            if ($options->isEmpty()) continue;

            $correctOption = $options->firstWhere('is_correct', true);
            $wrongOptions  = $options->where('is_correct', false);

            $answerCorrectly = $pass ? (rand(1, 100) <= 80) : (rand(1, 100) <= 30);

            if ($answerCorrectly && $correctOption) {
                $selectedOption = $correctOption;
            } else {
                $selectedOption = $wrongOptions->isNotEmpty()
                    ? $wrongOptions->random()
                    : $correctOption;
            }

            Answer::updateOrCreate(
                ['exam_session_id' => $session->id, 'question_id' => $question->id],
                ['option_id' => $selectedOption?->id]
            );
        }
    }

    private function fakeName(int $index): string
    {
        $names = [
            'Arya Wiratama', 'Bunga Lestari', 'Cahaya Putri',
            'Dimas Pratama', 'Eka Saputra', 'Fajar Nugroho',
            'Gita Anjani', 'Hendra Kurniawan', 'Indah Permata',
            'Joko Susanto', 'Kartika Sari', 'Lukman Hakim',
            'Maya Anggraeni', 'Naufal Rizky', 'Olivia Dewi',
        ];
        return $names[($index - 1) % count($names)];
    }
}
