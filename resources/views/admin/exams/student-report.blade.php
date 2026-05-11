@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.exams.results', $exam) }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-3xl font-bold text-gray-900 mb-1">Kartu Hasil Ujian</h2>
                <p class="text-gray-500">{{ $exam->title }} — {{ $exam->course->name ?? '-' }}</p>
            </div>
        </div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-printer text-lg"></i> Cetak
            </button>
        </div>
    </div>

    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 mb-8">
        <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
            <div class="h-16 w-16 rounded-full bg-[#e8eaf5] flex items-center justify-center text-primary">
                <i class="ph ph-user text-3xl"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-900">{{ $user->name }}</h3>
                <p class="text-sm text-gray-500">{{ $user->username }} — {{ $exam->classroom->name ?? '-' }}</p>
            </div>
        </div>

        @forelse($sessions as $attempt => $session)
            @php $isRemedial = $session->attempt_number > 1; @endphp
            <div class="mb-8 last:mb-0">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <span class="h-8 w-8 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold">
                            {{ $session->attempt_number }}
                        </span>
                        <div>
                            <h4 class="font-bold text-gray-900">
                                Percobaan {{ $session->attempt_number }}
                                @if($isRemedial)
                                    <span class="px-2 py-0.5 bg-[#eeedf7] text-secondary text-xs rounded-full font-medium ml-2">Remedial</span>
                                @endif
                            </h4>
                            <p class="text-xs text-gray-500">
                                @if($session->started_at)
                                    {{ $session->started_at->format('d M Y H:i:s') }}
                                @endif
                                @if($session->finished_at)
                                    — {{ $session->finished_at->format('H:i:s') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-bold {{ ($session->score ?? 0) >= $exam->passing_grade ? 'text-green-600' : 'text-red-500' }}">
                            {{ $session->score ?? '-' }}
                        </p>
                        <p class="text-xs text-gray-500">Nilai</p>
                    </div>
                </div>

                @if($session->answers->isNotEmpty())
                    <div class="space-y-3">
                        @php $questionNumber = 1; @endphp
                        @foreach($questions as $question)
                            @php
                                $answer = $session->answers->firstWhere('question_id', $question->id);
                                $selectedOption = $answer ? $answer->option : null;
                                $correctOption = $question->options->firstWhere('is_correct', true);
                                $isCorrect = $selectedOption && $selectedOption->is_correct;
                            @endphp
                            <div class="p-4 rounded-xl border {{ $isCorrect ? 'bg-green-50 border-green-200' : ($selectedOption ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200') }}">
                                <div class="flex items-start gap-3">
                                    <span class="h-7 w-7 rounded-full bg-white border border-gray-200 flex items-center justify-center text-xs font-bold {{ $isCorrect ? 'text-green-600 border-green-300' : ($selectedOption ? 'text-red-500 border-red-300' : 'text-gray-400') }} flex-shrink-0">
                                        {{ $questionNumber }}
                                    </span>
                                    <div class="flex-grow min-w-0">
                                        <p class="text-sm font-medium text-gray-900 mb-2">{{ $question->content }}</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5">
                                            @foreach($question->options as $option)
                                                <div class="px-3 py-1.5 rounded-lg text-xs border flex items-center gap-2
                                                    {{ $option->is_correct ? 'bg-green-100 border-green-300 text-green-800 font-semibold' : '' }}
                                                    {{ $selectedOption && $selectedOption->id === $option->id && !$option->is_correct ? 'bg-red-100 border-red-300 text-red-800 font-semibold' : '' }}
                                                    {{ !$option->is_correct && (!$selectedOption || $selectedOption->id !== $option->id) ? 'bg-white border-gray-200 text-gray-600' : '' }}
                                                ">
                                                    @if($option->is_correct)
                                                        <i class="ph-fill ph-check-circle text-sm"></i>
                                                    @elseif($selectedOption && $selectedOption->id === $option->id)
                                                        <i class="ph-fill ph-x-circle text-sm"></i>
                                                    @else
                                                        <i class="ph ph-circle text-xs"></i>
                                                    @endif
                                                    {{ $option->content }}
                                                </div>
                                            @endforeach
                                        </div>
                                        @if(!$selectedOption)
                                            <p class="text-xs text-gray-400 mt-2"><i class="ph ph-minus-circle"></i> Tidak dijawab</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @php $questionNumber++; @endphp
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 text-center py-6">Belum ada data jawaban untuk percobaan ini.</p>
                @endif
            </div>
        @empty
            <div class="text-center py-12 text-gray-500">
                <i class="ph ph-file-x text-4xl text-gray-300 mb-3"></i>
                <p>Mahasiswa belum mengerjakan ujian ini.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection