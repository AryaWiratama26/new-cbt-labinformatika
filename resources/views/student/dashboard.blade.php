@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-1">Dashboard Mahasiswa</h2>
        <p class="text-gray-500">Selamat datang, {{ auth()->user()->name }} (Kelas: {{ auth()->user()->classroom->name ?? '-' }})</p>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl"></i>
            {{ session('error') }}
        </div>
    @endif

    <h3 class="text-xl font-bold text-gray-900 mb-4">Jadwal Ujian Anda</h3>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($exams as $exam)
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col h-full">
                <div class="flex justify-between items-start mb-4">
                    <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-exam text-2xl"></i>
                    </div>
                    @if($exam->status === 'finished')
                        <span class="px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full border border-green-200">Selesai</span>
                    @elseif($exam->status === 'in_progress')
                        <span class="px-3 py-1 bg-yellow-50 text-yellow-700 text-xs font-semibold rounded-full border border-yellow-200">Sedang Dikerjakan</span>
                    @elseif($exam->status === 'waiting')
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full border border-gray-200">Belum Mulai</span>
                    @else
                        <span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-full border border-blue-200">Tersedia</span>
                    @endif
                </div>
                
                <h4 class="text-lg font-bold text-gray-900 mb-1">{{ $exam->title }}</h4>
                <p class="text-sm text-gray-500 mb-4">{{ $exam->course->name ?? '-' }}</p>
                
                <div class="space-y-2 mb-6 flex-grow">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <i class="ph ph-calendar-blank text-gray-400"></i>
                        <span>{{ $exam->start_time->format('d M Y') }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <i class="ph ph-clock text-gray-400"></i>
                        <span>{{ $exam->start_time->format('H:i') }} - {{ $exam->end_time->format('H:i') }} WIB</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <i class="ph ph-hourglass-high text-gray-400"></i>
                        <span>{{ $exam->duration_minutes }} Menit</span>
                    </div>
                </div>

                @if($exam->status === 'finished')
                    @php
                        $session = \App\Models\ExamSession::where('user_id', auth()->id())->where('exam_id', $exam->id)->first();
                    @endphp
                    <div class="w-full bg-gray-50 text-gray-700 py-3 rounded-xl font-medium text-center border border-gray-200">
                        Nilai: {{ $session->score ?? 0 }}
                    </div>
                @elseif($exam->status === 'in_progress')
                    <a href="{{ route('student.exams.show', $exam) }}" class="w-full bg-[#3b4d3b] hover:bg-[#2d3b2d] text-white py-3 rounded-xl font-medium transition-colors text-center block">
                        Lanjutkan Ujian
                    </a>
                @elseif($exam->status === 'available')
                    @if($exam->questions_count === 0)
                        <div class="w-full flex flex-col items-center gap-2">
                            <div class="flex items-center gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 px-3 py-2 rounded-lg w-full justify-center">
                                <i class="ph ph-warning text-base"></i>
                                Soal belum tersedia
                            </div>
                            <button disabled class="w-full bg-gray-200 text-gray-400 py-3 rounded-xl font-medium cursor-not-allowed">
                                Mulai Ujian
                            </button>
                        </div>
                    @else
                        <a href="{{ route('student.exams.show', $exam) }}" class="w-full bg-[#3b4d3b] hover:bg-[#2d3b2d] text-white py-3 rounded-xl font-medium transition-colors text-center block">
                            Mulai Ujian ({{ $exam->questions_count }} Soal)
                        </a>
                    @endif
                @elseif($exam->status === 'waiting')
                    <button disabled class="w-full bg-gray-200 text-gray-500 py-3 rounded-xl font-medium cursor-not-allowed">
                        Belum Waktunya
                    </button>
                @endif
            </div>
        @empty
            <div class="md:col-span-2 lg:col-span-3 text-center py-16 bg-white rounded-[2rem] border border-gray-100">
                <div class="inline-flex h-20 w-20 bg-gray-50 rounded-full items-center justify-center text-gray-400 mb-4">
                    <i class="ph ph-calendar-check text-4xl"></i>
                </div>
                <h4 class="text-xl font-bold text-gray-900 mb-2">Tidak ada jadwal ujian</h4>
                <p class="text-gray-500">Belum ada ujian praktikum yang dijadwalkan untuk kelas Anda saat ini.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
