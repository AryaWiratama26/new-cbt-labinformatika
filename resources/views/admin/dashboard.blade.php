@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Admin Dashboard</h2>
            <p class="text-gray-500">Ringkasan aktivitas CBT Universitas Pelita Bangsa</p>
        </div>
        <div class="text-right text-sm text-gray-500">
            <p>{{ now()->format('l, d F Y') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl"></i> {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <a href="{{ route('admin.students.index') }}" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:border-primary/20 hover:shadow-md transition-all group">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-xl bg-[#e8eaf5] flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all">
                    <i class="ph ph-users text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Mahasiswa</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $studentsCount }}</p>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.courses.index') }}" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:border-secondary/20 hover:shadow-md transition-all group">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-xl bg-[#e8eaf5] flex items-center justify-center text-secondary group-hover:bg-secondary group-hover:text-white transition-all">
                    <i class="ph ph-books text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Mata Kuliah</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $coursesCount }}</p>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.classrooms') }}" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:border-primary/20 hover:shadow-md transition-all group">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-xl bg-[#e8eaf5] flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all">
                    <i class="ph ph-chalkboard-teacher text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Total Kelas</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $classroomsCount }}</p>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.exams.index') }}" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:border-secondary/20 hover:shadow-md transition-all group">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-xl bg-[#e8eaf5] flex items-center justify-center text-secondary group-hover:bg-secondary group-hover:text-white transition-all">
                    <i class="ph ph-exam text-xl"></i>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Total Ujian</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $examsCount }}</p>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-primary/20">
            <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-1">Ujian Hari Ini</p>
            <p class="text-3xl font-bold text-gray-900">{{ $examsToday }}</p>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-secondary/20">
            <p class="text-xs font-semibold text-secondary uppercase tracking-wide mb-1">Sedang Berlangsung</p>
            <p class="text-3xl font-bold text-gray-900">{{ $activeExams }}</p>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-primary/20">
            <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-1">Rata-rata Nilai</p>
            <p class="text-3xl font-bold text-gray-900">{{ $avgScore ? number_format($avgScore, 1) : '-' }}</p>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-secondary/20">
            <p class="text-xs font-semibold text-secondary uppercase tracking-wide mb-1">Partisipasi</p>
            <p class="text-3xl font-bold text-gray-900">{{ $participationRate }}%</p>
        </div>
    </div>

    <div class="grid md:grid-cols-3 gap-8 mb-8">
        <div class="md:col-span-2 bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Distribusi Nilai</h3>
            <div class="grid grid-cols-4 gap-3">
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-500">{{ $scoreDistribution['below50'] }}</div>
                    <div class="h-2 w-full bg-red-100 rounded-full mt-1 overflow-hidden">
                        <div class="h-full bg-red-500 rounded-full" style="width: {{ max($scoreDistribution['below50'], $scoreDistribution['50to69'], $scoreDistribution['70to85'], $scoreDistribution['above85']) > 0 ? ($scoreDistribution['below50'] / max($scoreDistribution['below50'], $scoreDistribution['50to69'], $scoreDistribution['70to85'], $scoreDistribution['above85'], 1)) * 100 : 0 }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">&lt; 50</p>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-500">{{ $scoreDistribution['50to69'] }}</div>
                    <div class="h-2 w-full bg-orange-100 rounded-full mt-1 overflow-hidden">
                        <div class="h-full bg-orange-500 rounded-full" style="width: {{ max($scoreDistribution['below50'], $scoreDistribution['50to69'], $scoreDistribution['70to85'], $scoreDistribution['above85']) > 0 ? ($scoreDistribution['50to69'] / max($scoreDistribution['below50'], $scoreDistribution['50to69'], $scoreDistribution['70to85'], $scoreDistribution['above85'], 1)) * 100 : 0 }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">50-70</p>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-500">{{ $scoreDistribution['70to85'] }}</div>
                    <div class="h-2 w-full bg-yellow-100 rounded-full mt-1 overflow-hidden">
                        <div class="h-full bg-yellow-500 rounded-full" style="width: {{ max($scoreDistribution['below50'], $scoreDistribution['50to69'], $scoreDistribution['70to85'], $scoreDistribution['above85']) > 0 ? ($scoreDistribution['70to85'] / max($scoreDistribution['below50'], $scoreDistribution['50to69'], $scoreDistribution['70to85'], $scoreDistribution['above85'], 1)) * 100 : 0 }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">70-85</p>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-500">{{ $scoreDistribution['above85'] }}</div>
                    <div class="h-2 w-full bg-green-100 rounded-full mt-1 overflow-hidden">
                        <div class="h-full bg-green-500 rounded-full" style="width: {{ max($scoreDistribution['below50'], $scoreDistribution['50to69'], $scoreDistribution['70to85'], $scoreDistribution['above85']) > 0 ? ($scoreDistribution['above85'] / max($scoreDistribution['below50'], $scoreDistribution['50to69'], $scoreDistribution['70to85'], $scoreDistribution['above85'], 1)) * 100 : 0 }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">&gt; 85</p>
                </div>
            </div>

            <h3 class="text-lg font-bold text-gray-900 mt-8 mb-4">Top 5 Mahasiswa (Rata-rata Nilai)</h3>
            <div class="space-y-2">
                @forelse($topStudents as $i => $ts)
                    <div class="flex items-center gap-3 p-2 rounded-lg {{ $i < 3 ? 'bg-[#e8eaf5]' : '' }}">
                        <span class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600">{{ $i + 1 }}</span>
                        <span class="font-medium text-gray-900 flex-grow">{{ $ts->user->name ?? 'Unknown' }}</span>
                        <span class="font-bold text-primary">{{ number_format($ts->avg_score, 1) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">Belum ada data nilai</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Aktivitas Terakhir</h3>
            <div class="space-y-3">
                @forelse($recentSessions as $session)
                    <div class="flex items-start gap-3 p-2 border-b border-gray-50 last:border-0">
                        <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <i class="ph ph-user text-sm text-gray-500"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $session->user->name ?? '-' }}</p>
                            <p class="text-xs text-gray-500">{{ $session->exam->title ?? '-' }}</p>
                            <p class="text-xs text-gray-400">{{ $session->finished_at->diffForHumans() }}</p>
                        </div>
                        <span class="ml-auto font-bold text-sm {{ ($session->score ?? 0) >= ($session->exam->passing_grade ?? 70) ? 'text-green-600' : 'text-red-500' }}">{{ $session->score ?? 0 }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-8">Belum ada aktivitas</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-8">
        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-start mb-2">
                <h3 class="text-xl font-bold text-gray-900">Import Data Mahasiswa</h3>
                <a href="{{ route('admin.template_students') }}" class="text-sm text-primary hover:underline flex items-center gap-1 font-medium bg-[#e8eaf5] px-3 py-1 rounded-lg">
                    <i class="ph ph-download-simple"></i> Template CSV
                </a>
            </div>
            <p class="text-sm text-gray-500 mb-6">Unggah file CSV dengan format <code>NIM, Nama, Kelas</code>.</p>
            <form action="{{ route('admin.import_students') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center hover:bg-gray-50 transition-colors">
                    <i class="ph ph-upload-simple text-4xl text-gray-400 mb-2"></i>
                    <input type="file" name="csv_file" accept=".csv" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-[#e8eaf5] file:text-primary hover:file:bg-[#dde0f0] cursor-pointer">
                </div>
                @error('csv_file') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white py-3 rounded-xl font-medium transition-colors flex justify-center items-center gap-2">
                    <i class="ph ph-database"></i> Import Sekarang
                </button>
            </form>
        </div>

        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Akses Cepat</h3>
            <p class="text-sm text-gray-500 mb-6">Jalan pintas ke modul utama sistem.</p>
            <div class="space-y-3 flex-grow">
                <a href="{{ route('admin.exams.index') }}" class="group flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-primary/20 hover:bg-[#e8eaf5] transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 bg-[#e8eaf5] text-primary rounded-lg flex items-center justify-center"><i class="ph ph-exam text-xl"></i></div>
                        <div><p class="font-bold text-gray-900 group-hover:text-primary">Manajemen Ujian</p><p class="text-xs text-gray-500">Buat ujian &amp; kelola bank soal</p></div>
                    </div>
                    <i class="ph ph-caret-right text-gray-400 group-hover:text-primary"></i>
                </a>
                <a href="{{ route('admin.courses.index') }}" class="group flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-secondary/20 hover:bg-[#eeedf7] transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 bg-[#eeedf7] text-secondary rounded-lg flex items-center justify-center"><i class="ph ph-books text-xl"></i></div>
                        <div><p class="font-bold text-gray-900 group-hover:text-secondary">Mata Kuliah</p><p class="text-xs text-gray-500">Daftar &amp; modul matkul</p></div>
                    </div>
                    <i class="ph ph-caret-right text-gray-400 group-hover:text-secondary"></i>
                </a>
                <a href="{{ route('admin.students.index') }}" class="group flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-primary/20 hover:bg-[#e8eaf5] transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 bg-[#e8eaf5] text-primary rounded-lg flex items-center justify-center"><i class="ph ph-users text-xl"></i></div>
                        <div><p class="font-bold text-gray-900 group-hover:text-primary">Manajemen Mahasiswa</p><p class="text-xs text-gray-500">Edit, hapus, pindahkan kelas</p></div>
                    </div>
                    <i class="ph ph-caret-right text-gray-400 group-hover:text-primary"></i>
                </a>
                <a href="{{ route('admin.classrooms') }}" class="group flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-secondary/20 hover:bg-[#eeedf7] transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 bg-[#eeedf7] text-secondary rounded-lg flex items-center justify-center"><i class="ph ph-chalkboard-teacher text-xl"></i></div>
                        <div><p class="font-bold text-gray-900 group-hover:text-secondary">Kelas</p><p class="text-xs text-gray-500">CRUD kelas &amp; tahun ajaran</p></div>
                    </div>
                    <i class="ph ph-caret-right text-gray-400 group-hover:text-secondary"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
