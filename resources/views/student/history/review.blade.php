@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('student.history') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-3xl font-bold text-gray-900 mb-1">Review Jawaban</h2>
                <p class="text-gray-500">{{ $exam->title }} — {{ $exam->course->name ?? '-' }}</p>
            </div>
        </div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-printer text-lg"></i>
                Cetak
            </button>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 mb-8">
        <div class="flex flex-col md:flex-row md:items-center gap-4 md:gap-6 mb-6 pb-6 border-b border-gray-100">
            <div class="h-16 w-16 rounded-full bg-[#e8eaf5] flex items-center justify-center text-primary flex-shrink-0">
                <i class="ph ph-user text-3xl"></i>
            </div>
            <div class="flex-grow">
                <h3 class="text-xl font-bold text-gray-900">{{ auth()->user()->name }}</h3>
                <p class="text-sm text-gray-500">{{ auth()->user()->username }} — {{ auth()->user()->classroom->name ?? '-' }}</p>
            </div>
            <div class="text-right md:text-right">
                <p class="text-4xl font-bold {{ ($examSession->score ?? 0) >= ($exam->passing_grade ?? 70) ? 'text-green-600' : 'text-red-500' }}">
                    {{ $examSession->score ?? '-' }}
                </p>
                <p class="text-xs text-gray-500">Nilai Akhir</p>
            </div>
        </div>

        <div class="mb-6 flex items-center gap-4">
            <span class="h-8 w-8 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold">
                {{ $examSession->attempt_number }}
            </span>
            <div>
                <h4 class="font-bold text-gray-900">Percobaan {{ $examSession->attempt_number }}</h4>
                <p class="text-xs text-gray-500">
                    @if($examSession->started_at)
                        {{ $examSession->started_at->format('d M Y H:i:s') }}
                    @endif
                    @if($examSession->finished_at)
                        — {{ $examSession->finished_at->format('H:i:s') }}
                    @endif
                </p>
            </div>
            <div class="ml-auto flex items-center gap-3">
                @php $isPassed = ($examSession->score ?? 0) >= ($exam->passing_grade ?? 70); @endphp
                @if($isPassed)
                    <span class="px-4 py-1.5 bg-green-50 text-green-700 text-sm font-semibold rounded-full border border-green-200 flex items-center gap-1.5">
                        <i class="ph-fill ph-check-circle"></i> Lulus
                    </span>
                @else
                    <span class="px-4 py-1.5 bg-red-50 text-red-700 text-sm font-semibold rounded-full border border-red-200 flex items-center gap-1.5">
                        <i class="ph-fill ph-x-circle"></i> Tidak Lulus
                    </span>
                @endif
            </div>
        </div>

        @if($questions->isNotEmpty())
            @php
                $unansweredCount = 0;
                foreach ($questions as $q) {
                    if (!$answers->has($q->id)) $unansweredCount++;
                }
                $answeredCount = $totalQuestions - $unansweredCount;
                $wrongAnswered = $answeredCount - $correctCount;
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="p-4 bg-gray-50 rounded-xl md:col-span-1">
                    <div class="relative mx-auto" style="height: 180px; max-width: 180px;">
                        <canvas id="resultChart"></canvas>
                    </div>
                </div>
                <div class="md:col-span-2 flex flex-wrap gap-4 items-center p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <span class="h-3 w-3 rounded-full bg-green-500"></span>
                        Benar: <span class="font-bold text-gray-900">{{ $correctCount }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <span class="h-3 w-3 rounded-full bg-red-500"></span>
                        Salah: <span class="font-bold text-gray-900">{{ $wrongAnswered }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <span class="h-3 w-3 rounded-full bg-gray-300"></span>
                        Tidak dijawab: <span class="font-bold text-gray-900">{{ $unansweredCount }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 border-l border-gray-200 pl-4">
                        <i class="ph ph-question text-gray-400 text-lg"></i>
                        Total: <span class="font-bold text-gray-900">{{ $totalQuestions }}</span>
                    </div>
                </div>
            </div>

            @push('scripts')
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('resultChart').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Benar', 'Salah', 'Tidak Dijawab'],
                        datasets: [{
                            data: [{{ $correctCount }}, {{ $wrongAnswered }}, {{ $unansweredCount }}],
                            backgroundColor: ['#22c55e', '#ef4444', '#d1d5db'],
                            borderWidth: 0,
                            hoverOffset: 8,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        let total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        let pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                                        return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            });
            </script>
            @endpush

            <div class="space-y-4">
                @foreach($questions as $index => $question)
                    @php
                        $answer = $answers->get($question->id);
                        $selectedOption = $answer ? $answer->option : null;
                        $correctOption = $question->options->firstWhere('is_correct', true);
                        $isCorrect = $selectedOption && $selectedOption->is_correct;
                    @endphp
                    <div class="p-5 rounded-xl border {{ $isCorrect ? 'bg-green-50/50 border-green-200' : ($selectedOption ? 'bg-red-50/50 border-red-200' : 'bg-gray-50 border-gray-200') }}">
                        <div class="flex items-start gap-4">
                            <span class="h-8 w-8 rounded-full bg-white border flex items-center justify-center text-sm font-bold flex-shrink-0 {{ $isCorrect ? 'text-green-600 border-green-300' : ($selectedOption ? 'text-red-500 border-red-300' : 'text-gray-400 border-gray-300') }}">
                                {{ $index + 1 }}
                            </span>
                            <div class="flex-grow min-w-0">
                                <p class="text-sm font-medium text-gray-900 mb-2">{{ $question->content }}</p>

                                @if($question->image)
                                    <div class="mb-3">
                                        <img src="{{ asset('storage/' . $question->image) }}" alt="Gambar soal" class="max-w-full h-auto rounded-lg max-h-64 object-contain" loading="lazy">
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    @foreach($question->options as $option)
                                        <div class="px-3 py-2 rounded-lg text-sm border flex items-center gap-2
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
                                            <span>{{ $option->content }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                @if(!$selectedOption)
                                    <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                                        <i class="ph ph-minus-circle"></i>
                                        Tidak dijawab
                                    </p>
                                @endif

                                @if(!$isCorrect && $question->explanation)
                                    <div class="mt-3 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                                        <p class="text-xs font-semibold text-blue-700 mb-1 flex items-center gap-1">
                                            <i class="ph ph-lightbulb"></i>
                                            Pembahasan:
                                        </p>
                                        <p class="text-xs text-blue-800">{{ $question->explanation }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 text-gray-500">
                <i class="ph ph-file-x text-4xl text-gray-300 mb-3"></i>
                <p>Tidak ada soal pada ujian ini.</p>
            </div>
        @endif
    </div>
</div>
@endsection
