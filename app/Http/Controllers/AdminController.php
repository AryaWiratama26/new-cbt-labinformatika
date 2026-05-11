<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Classroom;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;

class AdminController extends Controller
{
    public function dashboard()
    {
        $studentsCount = User::where('role', 'mahasiswa')->count();
        $coursesCount = \App\Models\Course::count();
        $classroomsCount = \App\Models\Classroom::count();
        $examsCount = \App\Models\Exam::count();
        
        return view('admin.dashboard', compact('studentsCount', 'coursesCount', 'classroomsCount', 'examsCount'));
    }

    public function students()
    {
        $students = User::where('role', 'mahasiswa')->with('classroom')->paginate(15);
        return view('admin.students.index', compact('students'));
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

    public function destroyStudent(User $user)
    {
        if ($user->role !== 'mahasiswa') abort(403);
        $user->delete();
        return redirect()->route('admin.students.index')->with('success', 'Mahasiswa berhasil dihapus.');
    }

    public function classrooms()
    {
        $classrooms = Classroom::withCount('users')->paginate(15);
        return view('admin.classrooms', compact('classrooms'));
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

        $callback = function() use($columns) {
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
        
        $columns = array('Pertanyaan', 'Nama_File_Gambar', 'Opsi_A', 'Opsi_B', 'Opsi_C', 'Opsi_D', 'Opsi_Benar');

        $callback = function() use($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, ['Sebutkan warna primer?', '', 'Merah, Kuning, Biru', 'Hijau, Ungu', 'Hitam, Putih', 'Abu-abu', 'A']);
            fputcsv($file, ['Perhatikan gambar berikut, ini diagram apa?', 'diagram1.jpg', 'Use Case', 'Activity', 'Class', 'Sequence', 'B']);
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
        
        $data = array_map('str_getcsv', file($path));
        $header = array_shift($data); // assuming first row is header
        
        $importedCount = 0;

        foreach ($data as $row) {
            $nim = trim($row[0] ?? '');
            $nama = trim($row[1] ?? 'Mahasiswa');
            $kelas = trim($row[2] ?? 'Umum');

            if ($nim) {
                $classroom = Classroom::firstOrCreate(['name' => $kelas]);

                User::updateOrCreate(
                    ['username' => $nim],
                    [
                        'name' => $nama,
                        'password' => Hash::make($nim),
                        'role' => 'mahasiswa',
                        'classroom_id' => $classroom->id
                    ]
                );
                $importedCount++;
            }
        }

        return redirect()->back()->with('success', "$importedCount data mahasiswa berhasil diimpor.");
    }
}
