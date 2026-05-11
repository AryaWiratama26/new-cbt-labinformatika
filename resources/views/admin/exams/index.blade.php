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
                            @if($exam->is_active)
                                <span class="px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full border border-green-200">Aktif</span>
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
                            <h4 class="text-lg font-bold text-gray-900 mb-1">Belum ada ujian</h4>
                            <p class="text-sm">Silakan buat ujian baru terlebih dahulu.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
