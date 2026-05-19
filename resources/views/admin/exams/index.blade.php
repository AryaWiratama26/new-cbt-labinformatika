@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Manajemen Ujian</h2>
            <p class="text-gray-500">Buat jadwal ujian dan kelola soal-soalnya.</p>
        </div>
        <div>
            <a href="{{ route('admin.exams.create') }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-5 rounded-xl font-medium transition-colors shadow-sm">
                <i class="ph ph-plus-circle text-xl"></i> Buat Ujian Baru
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl"></i> {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
        <form method="GET" action="{{ route('admin.exams.index') }}" class="p-5 border-b border-gray-100">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari judul ujian..."
                            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    </div>
                </div>
                <select name="course_id" class="px-3 py-2 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <option value="">Semua Matkul</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" {{ request('course_id') == $course->id ? 'selected' : '' }}>
                            {{ $course->name }}
                        </option>
                    @endforeach
                </select>
                <select name="classroom_id" class="px-3 py-2 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <option value="">Semua Kelas</option>
                    @foreach($classrooms as $classroom)
                        <option value="{{ $classroom->id }}" {{ request('classroom_id') == $classroom->id ? 'selected' : '' }}>
                            {{ $classroom->name }}
                        </option>
                    @endforeach
                </select>
                <select name="status" class="px-3 py-2 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                    <option value="upcoming" {{ request('status') === 'upcoming' ? 'selected' : '' }}>Belum Mulai</option>
                    <option value="finished" {{ request('status') === 'finished' ? 'selected' : '' }}>Selesai</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium bg-primary text-white rounded-lg hover:bg-primary-hover transition-colors">
                        <i class="ph ph-faders"></i> Filter
                    </button>
                    <a href="{{ route('admin.exams.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="ph ph-x"></i> Reset
                    </a>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Judul Ujian</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Matkul & Kelas</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Waktu</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Status</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($exams as $exam)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-6">
                            <p class="font-bold text-gray-900">{{ $exam->title }}</p>
                            <p class="text-xs text-gray-500">{{ $exam->duration_minutes }} menit</p>
                        </td>
                        <td class="py-4 px-6">
                            <p class="font-medium text-gray-800">{{ $exam->course->name ?? '-' }}</p>
                            <span class="inline-block mt-1 px-2 py-0.5 bg-[#e8eaf5] text-primary text-xs rounded-md border border-primary/10">Kelas: {{ $exam->classroom->name ?? '-' }}</span>
                        </td>
                        <td class="py-4 px-6 text-sm text-gray-600">
                            <p><span class="font-medium">Mulai:</span> {{ $exam->start_time->format('d M Y, H:i') }}</p>
                            <p><span class="font-medium">Selesai:</span> {{ $exam->end_time->format('d M Y, H:i') }}</p>
                        </td>
                        <td class="py-4 px-6">
                            @if($exam->is_active && $exam->end_time > now())
                                @if($exam->start_time <= now())
                                    <span class="px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full border border-green-200">Sedang Berjalan</span>
                                @else
                                    <span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-full border border-blue-200">Belum Mulai</span>
                                @endif
                            @elseif($exam->end_time <= now())
                                <span class="px-3 py-1 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full border border-gray-200">Selesai</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full border border-gray-200">Nonaktif</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.exams.monitor', $exam) }}" class="text-primary hover:text-primary-hover p-2 rounded-lg hover:bg-[#e8eaf5] transition-colors" title="Monitor">
                                    <i class="ph ph-eye text-lg"></i>
                                </a>
                                <a href="{{ route('admin.exams.results', $exam) }}" class="text-secondary hover:text-secondary-hover p-2 rounded-lg hover:bg-[#eeedf7] transition-colors" title="Lihat Nilai">
                                    <i class="ph ph-chart-bar text-lg"></i>
                                </a>
                                <a href="{{ route('admin.exams.show', $exam) }}" class="text-primary hover:text-primary-hover p-2 rounded-lg hover:bg-[#e8eaf5] transition-colors" title="Kelola Soal">
                                    <i class="ph ph-list-numbers text-lg"></i>
                                </a>
                                <a href="{{ route('admin.exams.edit', $exam) }}" class="text-secondary hover:text-secondary-hover p-2 rounded-lg hover:bg-[#eeedf7] transition-colors" title="Edit">
                                    <i class="ph ph-pencil-simple text-lg"></i>
                                </a>
                                <form action="{{ route('admin.exams.destroy', $exam) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus ujian ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Hapus">
                                        <i class="ph ph-trash text-lg"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-gray-500">
                            <div class="inline-flex h-16 w-16 bg-gray-50 rounded-full items-center justify-center text-gray-400 mb-4">
                                <i class="ph ph-files text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-1">{{ (request('search') || request('course_id') || request('classroom_id') || request('status')) ? 'Tidak ada ujian ditemukan' : 'Belum ada ujian' }}</h4>
                            <p class="text-sm">{{ (request('search') || request('course_id') || request('classroom_id') || request('status')) ? 'Coba ubah filter pencarian Anda' : 'Silakan buat ujian baru terlebih dahulu.' }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($exams->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                    <p class="text-sm text-gray-500">
                        Menampilkan {{ $exams->firstItem() }}–{{ $exams->lastItem() }} dari {{ $exams->total() }} ujian
                    </p>
                    <div class="flex items-center gap-1">
                        @if($exams->onFirstPage())
                            <span class="px-3 py-1.5 text-sm rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed">
                                <i class="ph ph-caret-left"></i>
                            </span>
                        @else
                            <a href="{{ $exams->previousPageUrl() }}" class="px-3 py-1.5 text-sm rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100 transition-colors">
                                <i class="ph ph-caret-left"></i>
                            </a>
                        @endif

                        @foreach($exams->getUrlRange(max(1, $exams->currentPage() - 2), min($exams->lastPage(), $exams->currentPage() + 2)) as $page => $url)
                            @if($page == $exams->currentPage())
                                <span class="px-3 py-1.5 text-sm rounded-lg bg-primary text-white font-semibold">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="px-3 py-1.5 text-sm rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100 transition-colors">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if($exams->hasMorePages())
                            <a href="{{ $exams->nextPageUrl() }}" class="px-3 py-1.5 text-sm rounded-lg bg-gray-50 text-gray-700 hover:bg-gray-100 transition-colors">
                                <i class="ph ph-caret-right"></i>
                            </a>
                        @else
                            <span class="px-3 py-1.5 text-sm rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed">
                                <i class="ph ph-caret-right"></i>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
