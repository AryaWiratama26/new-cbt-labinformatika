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
                <p class="text-sm text-gray-500">{{ $user->username }} — {{ $user->classroom->name ?? '-' }}</p>
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
                            @if($question->isCoding())
                                @php $questionNumber++; @endphp
                                @continue
                            @endif
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

                @php
                    $codingQuestions = $questions->filter(fn ($q) => $q->isCoding());
                    $codingAnswers = $session->answers->filter(fn ($a) => $codingQuestions->contains('id', $a->question_id) && $a->answer_text !== null);
                @endphp
                @if($codingAnswers->isNotEmpty())
                    <form action="{{ route('admin.exams.ai_grade_coding', [$exam, $session]) }}" method="POST" class="mt-5 flex flex-wrap items-center gap-3 p-3 rounded-xl border border-blue-100 bg-blue-50/60 print:hidden">
                        @csrf
                        <p class="text-xs text-blue-800 flex-grow min-w-[12rem]"><i class="ph ph-robot"></i> Nilai semua jawaban koding di bawah memakai AI (maks 5 per klik, sisanya via cron). Nilai manual/AI sebelumnya tidak ditimpa kecuali opsi di samping dicentang.</p>
                        <label class="inline-flex items-center gap-1.5 text-xs text-blue-900 cursor-pointer whitespace-nowrap"><input type="checkbox" name="force" value="1" class="rounded"> Tulis ulang</label>
                        <button type="submit" class="inline-flex items-center gap-1.5 bg-secondary hover:bg-secondary/90 text-white text-xs font-semibold py-2 px-4 rounded-lg transition-colors">
                            <i class="ph-fill ph-play"></i> Nilai dengan AI
                        </button>
                    </form>
                    <form action="{{ route('admin.exams.grade_coding', [$exam, $session]) }}" method="POST" class="mt-3 space-y-3">
                        @csrf
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold text-gray-900 flex items-center gap-2"><i class="ph-fill ph-code text-secondary"></i> Penilaian Soal Koding</p>
                            @if($session->graded_at)
                                <span class="px-2.5 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">Sudah dinilai</span>
                            @else
                                <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-medium">Perlu dinilai</span>
                            @endif
                        </div>

                        @foreach($codingAnswers as $answer)
                            @php $question = $questions->firstWhere('id', $answer->question_id); @endphp
                            <div class="p-4 rounded-xl border border-gray-200 bg-gray-50/50">
                                <div class="flex flex-col md:flex-row items-start gap-4">
                                    <div class="flex-grow min-w-0 w-full">
                                        <p class="text-sm font-medium text-gray-900 mb-2">{{ $question->content ?? '-' }}</p>
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="px-2 py-0.5 text-xs rounded-full font-medium bg-secondary/10 text-secondary">{{ $question->languageLabel() ?? 'Kode' }}</span>
                                            <button type="button" class="admin-run-code-btn text-xs font-semibold text-white bg-secondary hover:bg-secondary/90 px-3 py-1 rounded-lg transition-colors inline-flex items-center gap-1 disabled:opacity-60 disabled:cursor-wait" data-answer="{{ $answer->id }}" data-question="{{ $answer->question_id }}">
                                                <i class="ph-fill ph-play"></i> Jalankan
                                            </button>
                                        </div>
                                        <pre class="p-3 rounded-lg bg-gray-900 text-gray-100 text-xs overflow-x-auto"><code>{{ $answer->answer_text }}</code></pre>
                                        @php $aiDetail = is_array($answer->ai_grade_detail ?? null) ? $answer->ai_grade_detail : []; @endphp
                                        @if(!empty($aiDetail))
                                            <div class="mt-2 rounded-lg border border-blue-100 bg-blue-50/60 p-3">
                                                <p class="text-xs font-semibold text-blue-800 mb-1 flex items-center gap-1"><i class="ph ph-robot"></i> Penilaian AI{{ !empty($aiDetail['model']) ? ' ('.$aiDetail['model'].')' : '' }}</p>
                                                @if(!empty($aiDetail['feedback']))
                                                    <p class="text-xs text-blue-900 whitespace-pre-wrap">{{ $aiDetail['feedback'] }}</p>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="admin-code-output mt-2 rounded-lg overflow-hidden border border-gray-800 print:hidden" data-answer="{{ $answer->id }}">
                                            <div class="flex items-center justify-between bg-gray-900 px-3 py-1.5">
                                                <span class="text-xs font-semibold text-gray-300 inline-flex items-center gap-1"><i class="ph ph-terminal-window"></i> Output</span>
                                                <button type="button" class="admin-clear-output text-xs font-medium text-gray-400 hover:text-white transition-colors" data-answer="{{ $answer->id }}">Bersihkan</button>
                                            </div>
                                            <pre class="admin-output-body bg-gray-950 text-gray-100 text-xs font-mono p-3 overflow-x-auto whitespace-pre-wrap max-h-64">Klik "Jalankan" untuk melihat output di sini.</pre>
                                            <div class="border-t border-gray-800 bg-gray-900 px-3 py-2.5">
                                                <div class="admin-code-input-chips flex flex-wrap items-center gap-1.5 mb-2" data-answer="{{ $answer->id }}"></div>
                                                <div class="flex items-center gap-2">
                                                    <input type="text" id="admin-stdin-{{ $answer->id }}" class="admin-code-input-line flex-grow text-xs font-mono rounded-lg bg-gray-950 text-gray-100 border border-gray-700 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none p-2.5" data-answer="{{ $answer->id }}" placeholder="Ketik input program, lalu Enter…" autocomplete="off">
                                                    <button type="button" class="admin-code-input-send text-xs font-semibold text-white bg-secondary hover:bg-secondary/90 px-3 py-2.5 rounded-lg transition-colors whitespace-nowrap" data-answer="{{ $answer->id }}">Kirim</button>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1.5">Jika program meminta input, ketik nilainya di kotak ini lalu Enter. Kode dijalankan ulang setiap kali input dikirim.</p>
                                            </div>
                                        </div>
                                        <textarea class="hidden admin-code-source" data-answer="{{ $answer->id }}">{{ $answer->answer_text }}</textarea>
                                    </div>
                                    <div class="w-full md:w-28 flex-shrink-0">
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nilai (0-100)</label>
                                        <input type="number" name="scores[{{ $answer->id }}]" min="0" max="100" step="1" value="{{ $answer->score !== null ? (float) $answer->score : '' }}" class="w-full px-3 py-2 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="0-100">
                                        <p class="text-xs mt-1 {{ $answer->grade_status === \App\Models\Answer::GRADE_GRADED ? 'text-green-600' : 'text-amber-600' }}">
                                            {{ $answer->grade_status === \App\Models\Answer::GRADE_GRADED ? 'Dinilai' : 'Belum dinilai' }}
                                        </p>
                                        @if($answer->graded_by === \App\Models\Answer::GRADED_BY_AI)
                                            <p class="text-xs mt-0.5 text-blue-600 font-medium">Otomatis</p>
                                        @elseif($answer->graded_by === \App\Models\Answer::GRADED_BY_MANUAL)
                                            <p class="text-xs mt-0.5 text-purple-600 font-medium">Manual</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-5 rounded-xl font-medium transition-colors text-sm">
                                <i class="ph ph-floppy-disk text-lg"></i> Simpan Penilaian
                            </button>
                        </div>
                    </form>
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

@push('scripts')
<script>
(function () {
    const runUrl = "{{ route('admin.exams.run_code', $exam) }}";
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    function boxFor(id) {
        return document.querySelector(`.admin-code-output[data-answer="${id}"]`);
    }

    function sourceFor(id) {
        return document.querySelector(`.admin-code-source[data-answer="${id}"]`);
    }

    const OUTPUT_PLACEHOLDER = 'Klik "Jalankan" untuk melihat output di sini.';
    const INPUT_HINT = '\n\n→ Program minta input. Ketik nilainya di kotak bawah, lalu Enter.';
    const adminInputs = {};
    const adminTranscript = {};
    const adminLastStdout = {};
    const INPUT_READ_RE = /(\binput\s*\(|scanf|fscanf|fgets\s*\(|\bgets\s*\(|cin\s*>>|std::cin|readline\s*\(|sys\.stdin|process\.stdin|stdin|Scanner\b|System\.in)/i;

    function looksLikeReadsInput(code) {
        return INPUT_READ_RE.test(code || '');
    }

    function inputLineFor(id) {
        return document.querySelector(`.admin-code-input-line[data-answer="${id}"]`);
    }

    function stdinFor(id) {
        return (adminInputs[id] || []).join('\n');
    }

    function renderChips(id) {
        const wrap = document.querySelector(`.admin-code-input-chips[data-answer="${id}"]`);
        if (!wrap) return;
        wrap.innerHTML = '';
        const items = adminInputs[id] || [];
        if (!items.length) return;
        items.forEach((val) => {
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center px-2 py-0.5 rounded-md bg-gray-800 text-gray-200 text-xs font-mono max-w-[14rem] truncate';
            chip.textContent = val;
            wrap.appendChild(chip);
        });
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'text-xs font-medium text-gray-400 hover:text-red-400 transition-colors ml-1';
        clear.textContent = 'Hapus semua';
        clear.addEventListener('click', () => {
            adminInputs[id] = [];
            adminTranscript[id] = '';
            adminLastStdout[id] = undefined;
            renderChips(id);
            showOutput(id, OUTPUT_PLACEHOLDER, false);
        });
        wrap.appendChild(clear);
    }

    function showOutput(id, text, isError, needsInput = false) {
        const box = boxFor(id);
        if (!box) return;
        const body = box.querySelector('.admin-output-body');
        box.classList.remove('hidden');
        const out = text || (needsInput ? '' : '(tidak ada output)');
        body.textContent = needsInput ? out + INPUT_HINT : out;
        body.classList.toggle('text-red-400', !!isError);
        body.classList.toggle('text-gray-100', !isError);
        if (needsInput) {
            const line = inputLineFor(id);
            if (line) line.focus();
        }
    }

    function submitInput(id) {
        const line = inputLineFor(id);
        if (!line) return;
        const value = line.value;
        if (value === '') return;
        adminInputs[id] = adminInputs[id] || [];
        adminInputs[id].push(value);
        line.value = '';
        renderChips(id);
        run(document.querySelector(`.admin-run-code-btn[data-answer="${id}"]`), value);
    }

    async function run(btn, echoValue) {
        const id = btn ? btn.dataset.answer : null;
        if (!id) return;
        const questionId = btn.dataset.question;
        const source = sourceFor(id);
        const code = source ? source.value : '';
        const stdin = stdinFor(id);
        const original = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-circle-notch animate-spin"></i> Menjalankan...';
        showOutput(id, 'Menjalankan kode...', false);

        try {
            const res = await fetch(runUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ question_id: questionId, code: code, stdin: stdin, _token: csrf })
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || data.message) {
                adminLastStdout[id] = undefined;
                showOutput(id, data.message || ('Gagal menjalankan kode (HTTP ' + res.status + ').'), true);
                return;
            }

            if (data.compile_output) {
                const combined = data.output ? data.compile_output + '\n' + data.output : data.compile_output;
                adminTranscript[id] = combined;
                adminLastStdout[id] = undefined;
                showOutput(id, combined, !data.ok);
                return;
            }

            const stdout = typeof data.stdout === 'string' ? data.stdout : (data.output || '');
            const prev = adminLastStdout[id];
            let transcript;

            if (echoValue !== undefined && typeof prev === 'string' && stdout.startsWith(prev)) {
                transcript = (adminTranscript[id] || '') + echoValue + '\n' + stdout.slice(prev.length);
            } else {
                transcript = stdout;
            }

            adminTranscript[id] = transcript;
            adminLastStdout[id] = stdout;

            const providedInput = (adminInputs[id] || []).length > 0;
            const needsInput = !!data.needs_input || (!providedInput && looksLikeReadsInput(code));
            showOutput(id, transcript, !data.ok && !needsInput, needsInput);
        } catch (e) {
            adminLastStdout[id] = undefined;
            showOutput(id, 'Tidak dapat menghubungi server. Periksa koneksi Anda.', true);
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }

    document.querySelectorAll('.admin-run-code-btn').forEach((btn) => {
        btn.addEventListener('click', function () { run(this); });
    });

    document.querySelectorAll('.admin-code-input-line').forEach((line) => {
        line.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitInput(this.dataset.answer);
            }
        });
    });

    document.querySelectorAll('.admin-code-input-send').forEach((btn) => {
        btn.addEventListener('click', function () {
            submitInput(this.dataset.answer);
        });
    });

    document.querySelectorAll('.admin-clear-output').forEach((btn) => {
        btn.addEventListener('click', function () {
            const box = boxFor(this.dataset.answer);
            if (!box) return;
            adminTranscript[this.dataset.answer] = '';
            adminLastStdout[this.dataset.answer] = undefined;
            const body = box.querySelector('.admin-output-body');
            body.textContent = OUTPUT_PLACEHOLDER;
            body.classList.remove('text-red-400');
            body.classList.add('text-gray-100');
        });
    });
})();
</script>
@endpush