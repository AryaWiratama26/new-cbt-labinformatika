<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Course;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;

class AdminController extends Controller
{
    public function dashboard()
    {
        $studentsCount = User::where('role', 'mahasiswa')->count();
        $coursesCount = Course::count();
        $classroomsCount = Classroom::count();
        $examsCount = Exam::count();

        $examsToday = Exam::whereDate('start_time', today())->count();
        $activeExams = Exam::where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->count();

        $avgScore = ExamSession::whereNotNull('score')->avg('score');
        $totalSessions = ExamSession::count();

        $participationRate = 0;
        $totalPossibleSessions = User::where('role', 'mahasiswa')->count() * Exam::where('is_active', true)->count();
        if ($totalPossibleSessions > 0) {
            $participationRate = round(($totalSessions / $totalPossibleSessions) * 100, 1);
        }

        $scoreDistribution = [
            'below50' => ExamSession::whereNotNull('score')->where('score', '<', 50)->count(),
            '50to70'  => ExamSession::whereNotNull('score')->whereBetween('score', [50, 70])->count(),
            '70to85'  => ExamSession::whereNotNull('score')->whereBetween('score', [70, 85])->count(),
            'above85' => ExamSession::whereNotNull('score')->where('score', '>', 85)->count(),
        ];

        $recentSessions = ExamSession::with(['user', 'exam'])
            ->whereNotNull('finished_at')
            ->latest('finished_at')
            ->take(10)
            ->get();

        $topStudents = ExamSession::with('user')
            ->whereNotNull('score')
            ->selectRaw('user_id, AVG(score) as avg_score')
            ->groupBy('user_id')
            ->orderByDesc('avg_score')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'studentsCount', 'coursesCount', 'classroomsCount', 'examsCount',
            'examsToday', 'activeExams', 'avgScore', 'participationRate',
            'scoreDistribution', 'recentSessions', 'topStudents'
        ));
    }

    public function students(Request $request)
    {
        $query = User::where('role', 'mahasiswa')->with('classroom');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }
        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        if ($request->boolean('ajax')) {
            $students = $query->get(['id', 'username', 'name', 'classroom_id']);
            return response()->json($students);
        }

        $students = $query->latest()->paginate(15)->withQueryString();
        $classrooms = Classroom::orderBy('name')->get();

        return view('admin.students.index', compact('students', 'classrooms'));
    }

    public function createStudent()
    {
        $classrooms = Classroom::all();
        return view('admin.students.create', compact('classrooms'));
    }

    public function storeStudent(Request $request)
    {
        $request->validate([
            'nim' => 'required|unique:users,username',
            'name' => 'required|string|max:255',
            'classroom_id' => 'required|exists:classrooms,id',
        ]);

        User::create([
            'username' => $request->nim,
            'name' => $request->name,
            'classroom_id' => $request->classroom_id,
            'role' => 'mahasiswa',
            'password' => Hash::make($request->nim),
        ]);

        return redirect()->route('admin.students.index')->with('success', 'Mahasiswa berhasil ditambahkan.');
    }

    public function editStudent(User $user)
    {
        if ($user->role !== 'mahasiswa') abort(403);
        $classrooms = Classroom::all();
        return view('admin.students.edit', compact('user', 'classrooms'));
    }

    public function updateStudent(Request $request, User $user)
    {
        if ($user->role !== 'mahasiswa') abort(403);

        $request->validate([
            'nim' => 'required|unique:users,username,' . $user->id,
            'name' => 'required|string|max:255',
            'classroom_id' => 'required|exists:classrooms,id',
        ]);

        $user->update([
            'username' => $request->nim,
            'name' => $request->name,
            'classroom_id' => $request->classroom_id,
        ]);

        return redirect()->route('admin.students.index')->with('success', 'Data mahasiswa berhasil diperbarui.');
    }

    public function resetPassword(User $user)
    {
        if ($user->role !== 'mahasiswa') abort(403);
        $user->update(['password' => Hash::make($user->username)]);
        return redirect()->route('admin.students.index')->with('success', "Password {$user->name} berhasil direset ke NIM.");
    }

    public function destroyStudent(User $user)
    {
        if ($user->role !== 'mahasiswa') abort(403);
        $user->delete();
        return redirect()->route('admin.students.index')->with('success', 'Mahasiswa berhasil dihapus.');
    }

    public function bulkDeleteStudents(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return redirect()->back()->with('error', 'Tidak ada mahasiswa yang dipilih.');
        }
        User::whereIn('id', $ids)->where('role', 'mahasiswa')->delete();
        return redirect()->back()->with('success', count($ids) . ' Mahasiswa berhasil dihapus.');
    }

    public function exportStudents(Request $request)
    {
        $query = User::where('role', 'mahasiswa')->with('classroom');
        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }
        $students = $query->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=data_mahasiswa.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['NIM', 'Nama', 'Kelas']);
            foreach ($students as $s) {
                fputcsv($file, [$s->username, $s->name, $s->classroom->name ?? '']);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function classrooms(Request $request)
    {
        $query = Classroom::withCount('users');
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        $classrooms = $query->orderBy('name')->paginate(15)->withQueryString();
        return view('admin.classrooms', compact('classrooms'));
    }

    public function storeClassroom(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:classrooms,name',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|in:Ganjil,Genap',
        ]);

        Classroom::create($request->only('name', 'academic_year', 'semester'));
        return redirect()->route('admin.classrooms')->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function updateClassroom(Request $request, Classroom $classroom)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:classrooms,name,' . $classroom->id,
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|in:Ganjil,Genap',
        ]);

        $classroom->update($request->only('name', 'academic_year', 'semester'));
        return redirect()->route('admin.classrooms')->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroyClassroom(Classroom $classroom)
    {
        if ($classroom->users()->count() > 0) {
            return redirect()->back()->with('error', 'Kelas tidak bisa dihapus karena masih memiliki mahasiswa.');
        }
        $classroom->delete();
        return redirect()->route('admin.classrooms')->with('success', 'Kelas berhasil dihapus.');
    }

    public function moveStudentsForm()
    {
        $classrooms = Classroom::withCount('users')->orderBy('name')->get();
        return view('admin.students.move', compact('classrooms'));
    }

    public function moveStudents(Request $request)
    {
        $request->validate([
            'from_classroom_id' => 'required|exists:classrooms,id|different:to_classroom_id',
            'to_classroom_id' => 'required|exists:classrooms,id',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:users,id',
        ]);

        User::whereIn('id', $request->student_ids)
            ->where('classroom_id', $request->from_classroom_id)
            ->update(['classroom_id' => $request->to_classroom_id]);

        return redirect()->route('admin.classrooms')->with('success', count($request->student_ids) . ' mahasiswa berhasil dipindahkan.');
    }

    public function downloadStudentTemplate()
    {
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=template_mahasiswa.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = array('NIM', 'Nama', 'Kelas');

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, ['312010...', 'John Doe', 'TI.22.A.1']);
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function downloadQuestionTemplate()
    {
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=template_soal.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Soal', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Kunci Jawaban', 'Kategori'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, ['Ibukota Indonesia adalah...', 'Jakarta', 'Surabaya', 'Bandung', 'Medan', 'A', 'Pengetahuan Umum']);
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function importStudents(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        $contents = file_get_contents($path);
        if (substr($contents, 0, 3) === "\xEF\xBB\xBF") {
            $contents = substr($contents, 3);
        }
        $data = array_map('str_getcsv', explode("\n", $contents));

        $header = array_shift($data);
        if ($header === null) {
            return redirect()->back()->with('error', 'File CSV kosong.');
        }

        $importedCount = 0;
        $duplicateCount = 0;
        $errorRows = [];

        foreach ($data as $index => $row) {
            if (!is_array($row) || count($row) < 3) continue;

            $nim = trim($row[0] ?? '');
            $nama = trim($row[1] ?? 'Mahasiswa');
            $kelas = trim($row[2] ?? 'Umum');

            if ($nim) {
                try {
                    $classroom = Classroom::firstOrCreate(['name' => $kelas]);

                    $existing = User::where('username', $nim)->first();
                    if ($existing) {
                        $duplicateCount++;
                        continue;
                    }

                    User::create([
                        'username' => $nim,
                        'name' => $nama,
                        'password' => Hash::make($nim),
                        'role' => 'mahasiswa',
                        'classroom_id' => $classroom->id,
                    ]);
                    $importedCount++;
                } catch (\Exception $e) {
                    $errorRows[] = "Baris " . ($index + 2) . ": {$nim} - {$e->getMessage()}";
                }
            }
        }

        $message = "{$importedCount} data berhasil diimpor.";
        if ($duplicateCount > 0) {
            $message .= " {$duplicateCount} duplikat dilewati.";
        }
        if (!empty($errorRows)) {
            $message .= " " . count($errorRows) . " error: " . implode('; ', array_slice($errorRows, 0, 3));
        }

        return redirect()->back()->with('success', $message);
    }
}
