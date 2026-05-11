@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('student.dashboard') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">
                {{ $hasUnfinished ? 'Lanjutkan Ujian' : ($canRemedial ? 'Remedial' : 'Persiapan Ujian') }}
            </h2>
            <p class="text-gray-500">Baca detail dan peraturan sebelum memulai.</p>
        </div>
    </div>

    @if($canRemedial && $exam->max_attempts > 1)
    <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-start gap-3">
        <i class="ph ph-warning text-xl flex-shrink-0"></i>
        <div class="text-sm">
            <strong>Remedial:</strong> Nilai Anda sebelumnya (<strong>{{ $lastSession->score }}</strong>) di bawah nilai minimal <strong>{{ $exam->passing_grade }}</strong>. Anda masih memiliki kesempatan remedial (Percobaan {{ $lastSession->attempt_number + 1 }} dari {{ $exam->max_attempts }}).
        </div>
    </div>
    @endif

    @if($hasUnfinished)
    <div class="mb-6 p-4 rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-800 flex items-start gap-3">
        <i class="ph ph-clock-counter-clockwise text-xl flex-shrink-0"></i>
        <div class="text-sm">
            <strong>Ujian belum selesai!</strong> Anda memiliki sesi ujian yang masih berjalan. Klik tombol di bawah untuk melanjutkan.
        </div>
    </div>
    @endif

    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 text-center mb-6">
        <div class="inline-flex items-center justify-center h-20 w-20 rounded-2xl bg-primary/10 text-primary mb-6">
            <i class="ph ph-exam text-4xl"></i>
        </div>
        
        <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $exam->title }}</h3>
        <p class="text-gray-600 mb-8">{{ $exam->course->name ?? '-' }}</p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-left mb-8 border-y border-gray-100 py-6">
            <div>
                <span class="block text-xs text-gray-500 mb-1">Tanggal</span>
                <span class="font-medium text-gray-900">{{ $exam->start_time->format('d M Y') }}</span>
            </div>
            <div>
                <span class="block text-xs text-gray-500 mb-1">Waktu</span>
                <span class="font-medium text-gray-900">{{ $exam->start_time->format('H:i') }} WIB</span>
            </div>
            <div>
                <span class="block text-xs text-gray-500 mb-1">Durasi</span>
                <span class="font-medium text-gray-900">{{ $exam->duration_minutes }} Menit</span>
            </div>
            <div>
                <span class="block text-xs text-gray-500 mb-1">Total Soal</span>
                <span class="font-medium text-gray-900">
                    {{ $exam->getQuestionsCount() }} Butir
                </span>
            </div>
        </div>

        @if($exam->max_attempts > 1)
        <div class="grid grid-cols-2 gap-4 text-left mb-8">
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <span class="block text-xs text-gray-500 mb-1">Nilai Minimal</span>
                <span class="font-bold text-lg text-gray-900">{{ $exam->passing_grade }}</span>
            </div>
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <span class="block text-xs text-gray-500 mb-1">Maksimal Percobaan</span>
                <span class="font-bold text-lg text-gray-900">{{ $exam->max_attempts }}x</span>
            </div>
        </div>
        @endif

        @if($exam->description)
            <div class="text-left bg-gray-50 p-6 rounded-2xl border border-gray-100 mb-8">
                <h4 class="font-bold text-gray-900 mb-2 flex items-center gap-2">
                    <i class="ph-fill ph-info text-primary"></i> Instruksi / Peraturan
                </h4>
                <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $exam->description }}</p>
            </div>
        @endif

        @if($hasUnfinished || $canRemedial || $lastSession === null)
        <form action="{{ route('student.exams.start', $exam) }}" method="POST">
            @csrf
            <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white py-4 rounded-xl font-bold transition-colors text-lg flex justify-center items-center gap-2">
                <i class="ph-fill ph-play-circle"></i> 
                @if($hasUnfinished)
                    Lanjutkan Pengerjaan
                @elseif($canRemedial)
                    Mulai Remedial (Percobaan {{ $lastSession->attempt_number + 1 }})
                @else
                    Mulai Kerjakan Sekarang
                @endif
            </button>
            <p class="text-xs text-gray-500 mt-3">Waktu akan mulai berjalan saat Anda mengklik tombol di atas.</p>
        </form>
        @elseif($lastSession && $lastSession->finished_at && !$canRemedial)
        <div class="w-full bg-gray-100 text-gray-500 py-4 rounded-xl font-medium text-center border border-gray-200 cursor-not-allowed">
            <i class="ph ph-check-circle text-xl inline-block mb-1"></i>
            <p>Anda sudah menyelesaikan ujian ini.</p>
            @if($lastSession->score >= $exam->passing_grade)
                <p class="text-sm text-green-600 mt-1">Nilai: {{ $lastSession->score }} (Lulus)</p>
            @else
                <p class="text-sm text-red-500 mt-1">Nilai: {{ $lastSession->score }} (Batas percobaan habis)</p>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
