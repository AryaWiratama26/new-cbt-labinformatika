@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.exams.index') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-3xl font-bold text-gray-900 mb-1">Laporan Nilai Ujian</h2>
                <p class="text-gray-500">Rekapitulasi nilai mahasiswa untuk ujian ini.</p>
            </div>
        </div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-printer text-lg"></i> Cetak Laporan
            </button>
        </div>
    </div>

    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 mb-8 print:shadow-none print:border-none print:p-0">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-6 pb-6 border-b border-gray-100">
            <div>
                <span class="block text-gray-500 mb-0.5">Judul Ujian</span>
                <span class="font-bold text-gray-900">{{ $exam->title }}</span>
            </div>
            <div>
                <span class="block text-gray-500 mb-0.5">Mata Kuliah</span>
                <span class="font-bold text-gray-900">{{ $exam->course->name ?? '-' }}</span>
            </div>
            <div>
                <span class="block text-gray-500 mb-0.5">Kelas</span>
                <span class="font-bold text-gray-900">{{ $exam->classroom->name ?? '-' }}</span>
            </div>
            <div>
                <span class="block text-gray-500 mb-0.5">Waktu Pelaksanaan</span>
                <span class="font-bold text-gray-900">{{ $exam->start_time->format('d/m/Y') }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[600px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="py-4 px-4 font-semibold text-gray-600 text-sm">No</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">NIM</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Nama Mahasiswa</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Waktu Submit</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm text-right">Skor / Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($sessions as $index => $session)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-4 text-gray-500">{{ $index + 1 }}</td>
                        <td class="py-4 px-6 font-mono text-gray-600">{{ $session->user->username }}</td>
                        <td class="py-4 px-6 font-medium text-gray-900">{{ $session->user->name }}</td>
                        <td class="py-4 px-6 text-sm text-gray-500">
                            {{ $session->finished_at ? $session->finished_at->format('d M Y, H:i:s') : 'Belum Selesai' }}
                        </td>
                        <td class="py-4 px-6 text-right font-bold {{ $session->score >= 70 ? 'text-green-600' : 'text-red-500' }}">
                            {{ $session->score ?? '0' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-gray-500">
                            <div class="inline-flex h-16 w-16 bg-gray-50 rounded-full items-center justify-center text-gray-400 mb-4">
                                <i class="ph ph-users text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-1">Belum ada peserta</h4>
                            <p class="text-sm">Belum ada mahasiswa yang mengerjakan ujian ini.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
