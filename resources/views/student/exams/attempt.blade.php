<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian CBT - {{ $exam->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/code-editor.js'])
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="text-gray-800 antialiased h-screen flex flex-col overflow-hidden">

    <!-- Top Navigation for Exam -->
    <header class="bg-white border-b border-gray-200 py-4 px-6 flex-shrink-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="h-10 w-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary font-bold">
                    <i class="ph-fill ph-student text-xl"></i>
                </div>
                <div>
                    <h1 class="font-bold text-gray-900 leading-tight">{{ $exam->title }}</h1>
                    <p class="text-xs text-gray-500">{{ auth()->user()->name }} ({{ auth()->user()->username }})</p>
                </div>
            </div>

            <!-- Timer -->
            <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-2 rounded-xl flex items-center gap-3 font-mono font-bold text-lg">
                <i class="ph ph-timer text-xl"></i>
                <span id="countdown-timer">00:00:00</span>
            </div>
        </div>
    </header>

    <!-- Save status bar — only shows for error/pending states that need user attention -->
    <div id="save-status-bar" class="fixed left-0 right-0 z-40 text-center text-xs font-medium py-1.5 transition-all duration-300" style="top: 4rem; transform: translateY(-100%); opacity: 0; pointer-events: none;"></div>

    <!-- Offline Banner -->
    <!-- UI fix: use 'hidden' class properly with flex; transition smoothly -->
    <div id="offline-banner" class="fixed left-0 right-0 z-[45] bg-gradient-to-r from-red-600 to-red-700 text-white text-center py-2.5 px-4 text-sm font-medium shadow-lg transition-all duration-300" style="top: 4rem; transform: translateY(-100%); opacity: 0; pointer-events: none;">
        <div class="flex items-center justify-center gap-2">
            <i class="ph ph-wifi-slash text-lg animate-pulse"></i>
            <span>Koneksi terputus — jawaban disimpan lokal, akan otomatis dikirim saat koneksi pulih.</span>
            <span id="offline-pending-count" class="bg-white/20 px-2 py-0.5 rounded-full text-xs ml-2">0 pending</span>
        </div>
    </div>

    @if($exam->require_fullscreen)
    <div id="attempt-fs-overlay" class="fixed inset-0 z-40 bg-white/95 flex items-center justify-center p-6 hidden">
        <div class="max-w-md w-full bg-white rounded-[2rem] shadow-xl border border-gray-100 p-8 text-center">
            <div class="inline-flex items-center justify-center h-20 w-20 rounded-2xl bg-secondary/10 text-secondary mb-6">
                <i class="ph-fill ph-arrows-out text-4xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Wajib Layar Penuh</h3>
            <p class="text-gray-500 mb-8">Anda harus berada dalam mode layar penuh untuk mengikuti ujian ini.</p>
            <button type="button" id="attempt-fs-btn" onclick="enterFs()" class="w-full bg-secondary hover:bg-secondary/90 text-white py-3.5 rounded-xl font-bold transition-colors flex items-center justify-center gap-2">
                <i class="ph-fill ph-arrows-out text-lg"></i> Masuk Layar Penuh
            </button>
            <p class="text-xs text-gray-400 mt-4">Setelah masuk layar penuh, soal akan ditampilkan.</p>
        </div>
    </div>
    @endif

    @if($exam->camera_proctoring && $exam->camera_phone)
    <div id="phone-camera-paused-overlay" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/60 p-6 backdrop-blur-sm" role="alert" aria-live="assertive">
        <div class="w-full max-w-md rounded-[2rem] bg-white p-8 text-center shadow-2xl">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                <i class="ph-fill ph-camera-slash text-3xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900">Ujian Dijeda</h3>
            <p class="mt-3 text-gray-600">Kamera HP terdeteksi dimatikan. Nyalakan kembali kamera HP untuk melanjutkan pengerjaan.</p>
            <p class="mt-4 text-xs text-gray-500">Halaman akan terbuka otomatis setelah kamera aktif.</p>
        </div>
    </div>
    @endif

    <!-- Main Content -->
    <main class="flex-grow flex overflow-hidden max-w-7xl mx-auto w-full" id="attempt-main">
        
        <!-- Questions Area -->
        <div class="flex-grow overflow-y-auto p-6 md:p-8 scroll-smooth" id="questions-container">
            <form id="exam-form" action="{{ route('student.exams.submit', $exam) }}" method="POST">
                @csrf

                @if(session('error'))
                    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3 max-w-3xl mx-auto">
                        <i class="ph ph-warning-circle text-xl"></i> {{ session('error') }}
                    </div>
                @endif
                @if(session('success'))
                    <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3 max-w-3xl mx-auto">
                        <i class="ph ph-check-circle text-xl"></i> {{ session('success') }}
                    </div>
                @endif

                <div class="space-y-12 max-w-3xl mx-auto pb-24">
                    @foreach($questions as $index => $question)
                        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 question-card" id="q-{{ $question->id }}">
                            <div class="flex gap-4 mb-6">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-lg border border-primary/20">
                                    {{ $index + 1 }}
                                </div>
                                <div class="flex-grow pt-1.5">
                                    @if($question->isCoding())
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-secondary/10 text-secondary text-xs font-semibold">
                                                <i class="ph-fill ph-code"></i> Soal Koding
                                            </span>
                                            @if($question->languageLabel())
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-medium">
                                                    <i class="ph ph-terminal-window"></i> {{ $question->languageLabel() }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                    <p class="text-gray-900 text-lg font-medium leading-relaxed whitespace-pre-wrap">{{ $question->content }}</p>
                                </div>
                            </div>

                            @if($question->image)
                                <div class="ml-14 mb-6">
                                    <img src="{{ asset('storage/' . $question->image) }}" alt="Gambar Soal" class="max-h-64 rounded-xl border border-gray-200 p-1 bg-gray-50">
                                </div>
                            @endif

                            @if($question->isCoding())
                                @php
                                    $codeValue = $existingAnswers[$question->id]
                                        ?? $codeAnswers[$question->id]
                                        ?? $question->starter_code
                                        ?? '';
                                @endphp
                                <div class="ml-14">
                                    <div class="rounded-2xl overflow-hidden border border-gray-800 shadow-sm">
                                        <div class="flex items-center justify-between bg-gray-900 px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                                                <span class="h-2.5 w-2.5 rounded-full bg-yellow-400"></span>
                                                <span class="h-2.5 w-2.5 rounded-full bg-green-400"></span>
                                                <span class="ml-2 text-xs font-medium text-gray-300">{{ $question->languageLabel() ?? 'Kode' }}</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <button type="button" class="run-code-btn text-xs font-semibold text-white bg-secondary hover:bg-secondary/90 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1 disabled:opacity-60 disabled:cursor-wait" data-qid="{{ $question->id }}">
                                                    <i class="ph-fill ph-play"></i> Jalankan
                                                </button>
                                                <button type="button" class="reset-code-btn text-xs font-medium text-gray-400 hover:text-white transition-colors flex items-center gap-1" data-qid="{{ $question->id }}">
                                                    <i class="ph ph-arrow-counter-clockwise"></i> Reset kode awal
                                                </button>
                                            </div>
                                        </div>
                                        <div class="code-editor" data-qid="{{ $question->id }}" data-language="{{ $question->code_language }}" data-autosave="true">
                                            <textarea class="code-input hidden">{{ $codeValue }}</textarea>
                                        </div>
                                    </div>

                                    <div class="code-output mt-3 rounded-2xl overflow-hidden border border-gray-800" data-qid="{{ $question->id }}">
                                        <div class="flex items-center justify-between bg-gray-900 px-4 py-2">
                                            <span class="text-xs font-semibold text-gray-300 flex items-center gap-1">
                                                <i class="ph ph-terminal-window"></i> Output
                                            </span>
                                            <button type="button" class="clear-output-btn text-xs font-medium text-gray-400 hover:text-white transition-colors flex items-center gap-1" data-qid="{{ $question->id }}">
                                                <i class="ph ph-x"></i> Bersihkan
                                            </button>
                                        </div>
                                        <pre class="output-body bg-gray-950 text-gray-100 text-xs font-mono p-4 overflow-x-auto whitespace-pre-wrap max-h-64">Klik "Jalankan" untuk melihat output di sini.</pre>
                                        <div class="border-t border-gray-800 bg-gray-900 px-4 py-3">
                                            <div class="code-input-chips flex flex-wrap items-center gap-1.5 mb-2" data-qid="{{ $question->id }}"></div>
                                            <div class="flex items-center gap-2">
                                                <input type="text" id="stdin-{{ $question->id }}" class="code-input-line flex-grow text-xs font-mono rounded-lg bg-gray-950 text-gray-100 border border-gray-700 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none p-2.5" data-qid="{{ $question->id }}" placeholder="Ketik input program di sini, lalu Enter…" autocomplete="off">
                                                <button type="button" class="code-input-send text-xs font-semibold text-white bg-secondary hover:bg-secondary/90 px-3 py-2.5 rounded-lg transition-colors flex items-center gap-1 whitespace-nowrap" data-qid="{{ $question->id }}">
                                                    <i class="ph ph-arrow-elbow-down-left"></i> Kirim
                                                </button>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1.5">Jika program meminta input, ketik nilainya di kotak ini lalu Enter. Kode dijalankan ulang setiap kali Anda mengirim input.</p>
                                        </div>
                                    </div>

                                    <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                                        <i class="ph ph-floppy-disk"></i> Kode tersimpan otomatis saat Anda mengetik.
                                    </p>
                                    @if($question->code_language === 'java')
                                        <p class="text-xs text-amber-600 mt-2 flex items-center gap-1">
                                            <i class="ph ph-info"></i> Java: gunakan <span class="font-mono font-semibold">class Main</span> (tanpa <span class="font-mono">public</span>) agar kode bisa dijalankan.
                                        </p>
                                    @endif
                                    <textarea name="answers_text[{{ $question->id }}]" class="code-submit hidden">{{ $codeValue }}</textarea>
                                    <script type="application/json" class="code-seed" data-qid="{{ $question->id }}">@json($question->starter_code ?? '')</script>
                                </div>
                            @else
                                <div class="ml-14 space-y-3">
                                    @foreach($question->options as $option)
                                        @php
                                            $isChecked = isset($existingAnswers[$question->id]) && $existingAnswers[$question->id] == $option->id;
                                        @endphp
                                        <label class="flex items-center p-4 border rounded-xl cursor-pointer transition-colors hover:bg-gray-50 option-label {{ $isChecked ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-gray-200' }}">
                                            <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}" class="w-5 h-5 text-primary border-gray-300 focus:ring-primary focus:ring-2 option-radio" {{ $isChecked ? 'checked' : '' }}>
                                            <span class="ml-3 text-gray-700">{{ $option->content }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </form>
        </div>

        <!-- Sidebar Navigation (Optional/Desktop only) -->
        <div class="hidden lg:block w-80 flex-shrink-0 border-l border-gray-200 bg-white p-6 overflow-y-auto">
            <h3 class="font-bold text-gray-900 mb-4">Navigasi Soal</h3>
            <div class="grid grid-cols-5 gap-2">
                @foreach($questions as $index => $question)
                    @php
                        $isAnswered = isset($existingAnswers[$question->id])
                            || (isset($codeAnswers[$question->id]) && trim((string) $codeAnswers[$question->id]) !== '');
                    @endphp
                    <a href="#q-{{ $question->id }}" class="nav-btn flex items-center justify-center h-10 rounded-lg text-sm font-semibold transition-colors border {{ $isAnswered ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:border-primary hover:text-primary' }}" data-qid="{{ $question->id }}">
                        {{ $index + 1 }}
                    </a>
                @endforeach
            </div>

            <div class="mt-12">
                <button type="button" form="exam-form" onclick="confirmSubmit()" class="w-full bg-primary hover:bg-primary-hover text-white py-3.5 rounded-xl font-bold transition-colors flex justify-center items-center gap-2 shadow-sm">
                    <i class="ph-fill ph-check-circle text-xl"></i> Kumpulkan Ujian
                </button>
            </div>
        </div>

    </main>

    <!-- Mobile Submit Button (Sticky Bottom) -->
    <div class="lg:hidden fixed bottom-0 left-0 right-0 p-4 bg-white border-t border-gray-200 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] z-20">
        <button type="button" form="exam-form" onclick="confirmSubmit()" class="w-full bg-primary hover:bg-primary-hover text-white py-3.5 rounded-xl font-bold transition-colors flex justify-center items-center gap-2">
            <i class="ph-fill ph-check-circle text-xl"></i> Kumpulkan Ujian
        </button>
    </div>

    <!-- Submit Confirmation Modal -->
    <div id="submit-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" style="backdrop-filter: blur(4px);">
        <div class="bg-white rounded-[2rem] shadow-2xl max-w-md w-full p-8 animate-[fadeIn_0.2s_ease-out]">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100 text-yellow-600 mb-4">
                    <i class="ph ph-warning-circle text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">Kumpulkan Ujian?</h3>
                <p class="text-gray-500 mt-1">Pastikan Anda sudah menjawab semua soal.</p>
            </div>

            <div class="bg-gray-50 rounded-2xl p-5 mb-6 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Soal Terjawab</span>
                    <span class="font-bold text-gray-900" id="summary-answered">0</span>
                </div>
                <div class="flex items-center justify-between border-t border-gray-200 pt-3">
                    <span class="text-gray-600">Total Soal</span>
                    <span class="font-bold text-gray-900" id="summary-total">0</span>
                </div>
                <div id="summary-remaining" class="text-sm text-green-600 font-medium pt-1"></div>
                <div id="summary-answered-detail" class="text-xs text-gray-400"></div>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeModal()" class="flex-1 py-3.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-colors">
                    <i class="ph ph-x-circle text-lg inline-block mr-1.5 align-middle"></i> Batal
                </button>
                <button type="button" id="do-submit-btn" onclick="doSubmit()" class="flex-1 py-3.5 bg-primary hover:bg-primary-hover text-white rounded-xl font-bold transition-colors flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-wait">
                    <i class="ph-fill ph-check-circle text-lg"></i> Kumpulkan
                </button>
            </div>
        </div>
    </div>

    <!-- Sync Progress Modal -->
    <div id="sync-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" style="backdrop-filter:blur(4px);">
        <div class="bg-white rounded-[2rem] shadow-2xl max-w-sm w-full p-8 text-center">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-blue-600 mb-4 animate-pulse">
                <i class="ph ph-cloud-arrow-up text-3xl"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Menyinkronkan Jawaban</h3>
            <p class="text-gray-500 text-sm mb-4" id="sync-status">Mengirim jawaban yang tersimpan lokal...</p>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div id="sync-progress" class="bg-primary h-2 rounded-full transition-all duration-300" style="width:0%"></div>
            </div>
        </div>
    </div>

    <!-- Navigation Legend (sidebar) -->
    <div id="nav-legend" class="hidden lg:block mt-6 px-5">
        <div class="bg-gray-50/80 rounded-xl p-3.5 border border-gray-100">
            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Keterangan</h4>
            <div class="space-y-2.5">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-primary text-white text-[10px] shadow-sm">
                        <i class="ph-bold ph-check"></i>
                    </span>
                    <span class="text-xs text-gray-600 font-medium">Tersimpan di server</span>
                </div>
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-amber-400 text-white text-[10px] shadow-sm">
                        <i class="ph-bold ph-cloud-arrow-up"></i>
                    </span>
                    <span class="text-xs text-gray-600 font-medium">Tersimpan lokal</span>
                </div>
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-white border border-gray-200 text-gray-300 text-[10px]">
                        <i class="ph ph-minus"></i>
                    </span>
                    <span class="text-xs text-gray-600 font-medium">Belum dijawab</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Logic Script -->
    <script>
        // ── Offline Queue (localStorage-backed) ──
        class OfflineQueue {
            constructor(examId, sessionId) {
                this.storageKey = `cbt_pending_${examId}_${sessionId}`;
                this.data = this._load();
                this.isFlushing = false;
            }

            _load() {
                try {
                    const raw = localStorage.getItem(this.storageKey);
                    return raw ? JSON.parse(raw) : { answers: {}, tab_switches: 0, pending_submit: false, last_flush: 0 };
                } catch { return { answers: {}, tab_switches: 0, pending_submit: false, last_flush: 0 }; }
            }

            _save() {
                try { localStorage.setItem(this.storageKey, JSON.stringify(this.data)); }
                catch (e) { console.warn('localStorage full:', e); }
            }

            addAnswer(questionId, optionId, answerText) {
                this.data.answers[questionId] = {
                    option_id: optionId ? parseInt(optionId) : null,
                    answer_text: typeof answerText === 'string' ? answerText : null,
                    saved_at: Date.now(),
                };
                this._save();
            }

            removeAnswer(questionId) {
                delete this.data.answers[questionId];
                this._save();
            }

            incrementTabSwitch() {
                this.data.tab_switches++;
                this._save();
            }

            setPendingSubmit(val) {
                this.data.pending_submit = val;
                this._save();
            }

            getPendingAnswers() {
                return Object.entries(this.data.answers).map(([qid, data]) => ({
                    question_id: parseInt(qid),
                    option_id: data.option_id ?? null,
                    answer_text: data.answer_text ?? null,
                }));
            }

            getPendingCount() { return Object.keys(this.data.answers).length; }
            getTabSwitches() { return this.data.tab_switches; }
            hasPendingSubmit() { return this.data.pending_submit; }
            hasPending() { return this.getPendingCount() > 0 || this.data.tab_switches > 0 || this.data.pending_submit; }

            clearAnswers() { this.data.answers = {}; this._save(); }
            clearTabSwitches() { this.data.tab_switches = 0; this._save(); }
            clearAll() { localStorage.removeItem(this.storageKey); this.data = this._load(); }
        }

        // ── Init ──
        const examId = {{ $exam->id }};
        const sessionId = {{ $session->id }};
        const remainingSeconds = {{ max(0, $endTime->timestamp - now()->timestamp) }};
        const localStartTime = Date.now();
        let isSubmitting = false;

        const timerElement = document.getElementById('countdown-timer');
        const form = document.getElementById('exam-form');
        const csrfToken = document.querySelector('input[name="_token"]').value;
        const saveUrl = "{{ route('student.exams.save_answer', $exam) }}";
        const runUrl = "{{ route('student.exams.run_code', $exam) }}";
        const tabSwitchUrl = "{{ route('student.exams.tab_switch', $exam) }}";
        const syncUrl = "{{ route('student.exams.sync', $exam) }}";
        const dashboardUrl = "{{ route('student.dashboard') }}";

        const offlineQueue = new OfflineQueue(examId, sessionId);
        let saving = false;

        // ── Nav Button 3-State ──
        function updateNavButton(questionId, status) {
            const btn = document.querySelector(`.nav-btn[data-qid="${questionId}"]`);
            if (!btn) return;

            btn.classList.remove('bg-primary', 'text-white', 'border-primary',
                                  'bg-amber-400', 'text-white', 'border-amber-400',
                                  'bg-white', 'text-gray-600', 'border-gray-200');

            if (status === 'confirmed') {
                btn.classList.add('bg-primary', 'text-white', 'border-primary');
            } else if (status === 'pending') {
                btn.classList.add('bg-amber-400', 'text-white', 'border-amber-400');
            } else if (status === 'unanswered') {
                btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
            }
        }

        // ── Save Status Bar ──
        // Only shows for critical states (pending/error) — normal saving/saved uses nav button feedback
        let saveStatusTimer = null;
        function showSaveStatus(type) {
            const bar = document.getElementById('save-status-bar');
            if (!bar) return;
            clearTimeout(saveStatusTimer);

            // 'saving' and 'saved' are silent — nav button colors handle the feedback
            if (type === 'saving' || type === 'saved') {
                hideSaveStatusBar();
                return;
            }

            bar.className = 'fixed left-0 right-0 z-40 text-center text-xs font-medium py-1.5 transition-all duration-300';
            bar.style.top = offlineBannerVisible ? 'calc(4rem + 40px)' : '4rem';

            if (type === 'pending') {
                bar.className += ' bg-amber-50 text-amber-700 border-b border-amber-200';
                bar.innerHTML = '<i class="ph ph-cloud-slash inline-block mr-1"></i> Jawaban tersimpan lokal — akan dikirim saat koneksi pulih';
                showSaveStatusBar();
                saveStatusTimer = setTimeout(() => hideSaveStatusBar(), 4000);
            } else if (type === 'error') {
                bar.className += ' bg-red-50 text-red-700 border-b border-red-200';
                bar.innerHTML = '<i class="ph ph-warning-circle inline-block mr-1"></i> Gagal menyimpan — periksa koneksi internet Anda';
                showSaveStatusBar();
                saveStatusTimer = setTimeout(() => hideSaveStatusBar(), 5000);
            }
        }

        function showSaveStatusBar() {
            const bar = document.getElementById('save-status-bar');
            if (!bar) return;
            bar.style.transform = 'translateY(0)';
            bar.style.opacity = '1';
            bar.style.pointerEvents = 'auto';
        }
        function hideSaveStatusBar() {
            const bar = document.getElementById('save-status-bar');
            if (!bar) return;
            bar.style.transform = 'translateY(-100%)';
            bar.style.opacity = '0';
            bar.style.pointerEvents = 'none';
        }

        // ── Offline Banner ──
        function updatePendingBadge() {
            const badge = document.getElementById('offline-pending-count');
            if (badge) badge.textContent = `${offlineQueue.getPendingCount()} pending`;
        }

        // ── Offline Banner (slide animation) ──
        let offlineBannerVisible = false;
        function showOfflineBanner() {
            const banner = document.getElementById('offline-banner');
            if (!banner || offlineBannerVisible) return;
            offlineBannerVisible = true;
            banner.style.transform = 'translateY(0)';
            banner.style.opacity = '1';
            banner.style.pointerEvents = 'auto';
            // Push save-status-bar below the offline banner
            const bar = document.getElementById('save-status-bar');
            if (bar) bar.style.top = 'calc(4rem + 40px)';
        }
        function hideOfflineBanner() {
            const banner = document.getElementById('offline-banner');
            if (!banner || !offlineBannerVisible) return;
            offlineBannerVisible = false;
            banner.style.transform = 'translateY(-100%)';
            banner.style.opacity = '0';
            banner.style.pointerEvents = 'none';
            // Reset save-status-bar position
            const bar = document.getElementById('save-status-bar');
            if (bar) bar.style.top = '4rem';
        }

        // ── Sync Modal ──
        function showSyncModal() {
            document.getElementById('sync-modal')?.classList.remove('hidden');
        }
        function hideSyncModal() {
            document.getElementById('sync-modal')?.classList.add('hidden');
        }

        // ── saveAnswer (offline-resilient) ──
        let pendingSaves = {};  // NEW-15 fix: map instead of single item

        async function saveAnswer(questionId, optionId, answerText = null) {
            offlineQueue.addAnswer(questionId, optionId, answerText);
            updateNavButton(questionId, 'pending');
            updatePendingBadge();

            if (!navigator.onLine) {
                showSaveStatus('pending');
                return;
            }

            if (saving) { pendingSaves[questionId] = { optionId, answerText }; return; }
            saving = true;
            showSaveStatus('saving');

            try {
                const res = await fetch(saveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({ question_id: questionId, option_id: optionId, answer_text: answerText, _token: csrfToken })
                });

                if (res.ok) {
                    offlineQueue.removeAnswer(questionId);
                    updateNavButton(questionId, 'confirmed');
                    showSaveStatus('saved');
                } else if (res.status === 419) {
                    showSaveStatus('error');
                } else if (res.status === 403) {
                    const err = await res.json().catch(() => ({}));
                    if (err.expired) {
                        alert('Waktu ujian telah habis. Jawaban Anda akan otomatis dikumpulkan.');
                        submitForm();
                        return;
                    }
                    showSaveStatus('pending');
                } else {
                    showSaveStatus('pending');
                }
            } catch (e) {
                showSaveStatus('pending');
            } finally {
                saving = false;
                // NEW-15 fix: process next pending save from the map
                const nextKey = Object.keys(pendingSaves)[0];
                if (nextKey) {
                    const next = pendingSaves[nextKey];
                    delete pendingSaves[nextKey];
                    saveAnswer(nextKey, next.optionId, next.answerText);
                }
                updatePendingBadge();
            }
        }

        // ── Code autosave (debounced) ──
        const codeSaveTimers = {};
        function saveCodeAnswer(questionId, value) {
            updateNavButton(questionId, value && value.trim() ? 'pending' : 'unanswered');
            clearTimeout(codeSaveTimers[questionId]);
            codeSaveTimers[questionId] = setTimeout(() => saveAnswer(questionId, null, value), 800);
        }
        window.CBTSaveCode = saveCodeAnswer;

        // ── Code editor helpers ──
        function syncCodeInputs() {
            document.querySelectorAll('.code-submit').forEach((ta) => {
                const match = ta.name.match(/\[(.*?)\]/);
                if (!match) return;
                const editorEl = document.querySelector(`.code-editor[data-qid="${match[1]}"]`);
                if (editorEl && window.CBTEditor) {
                    ta.value = window.CBTEditor.getValue(editorEl);
                }
            });
        }

        function submitForm() {
            if (isSubmitting) return;
            isSubmitting = true;
            try {
                syncCodeInputs();
            } catch (e) {
                console.warn('Sinkron kode gagal, lanjut kumpulkan:', e);
            }
            try {
                if (proctorLaptop) {
                    proctorLaptop.stop();
                }
            } catch (e) {
                console.warn('Stop proctoring gagal, lanjut kumpulkan:', e);
            }
            @if($exam->camera_phone)
            try {
                if (phoneCameraStatusTimer) clearInterval(phoneCameraStatusTimer);
            } catch (e) {}
            @endif
            form.submit();
        }

        document.querySelectorAll('.reset-code-btn').forEach((btn) => {
            btn.addEventListener('click', function () {
                const qid = this.dataset.qid;
                const seed = document.querySelector(`.code-seed[data-qid="${qid}"]`);
                const starter = seed ? JSON.parse(seed.textContent) : '';
                const editorEl = document.querySelector(`.code-editor[data-qid="${qid}"]`);
                if (editorEl && window.CBTEditor) {
                    window.CBTEditor.setValue(editorEl, starter);
                }
                saveCodeAnswer(qid, starter);
            });
        });

        // ── Run code (Wandbox sandbox, debug only, not saved) ──
        const OUTPUT_PLACEHOLDER = 'Klik "Jalankan" untuk melihat output di sini.';
        const INPUT_HINT = '\n\n→ Program minta input. Ketik nilainya di kotak bawah, lalu Enter.';
        const codeInputs = {};
        const codeTranscript = {};
        const codeLastStdout = {};
        const INPUT_READ_RE = /(\binput\s*\(|scanf|fscanf|fgets\s*\(|\bgets\s*\(|cin\s*>>|std::cin|readline\s*\(|sys\.stdin|process\.stdin|stdin|Scanner\b|System\.in)/i;

        function looksLikeReadsInput(code) {
            return INPUT_READ_RE.test(code || '');
        }

        function outputBoxFor(qid) {
            return document.querySelector(`.code-output[data-qid="${qid}"]`);
        }

        function inputLineFor(qid) {
            return document.querySelector(`.code-input-line[data-qid="${qid}"]`);
        }

        function stdinFor(qid) {
            return (codeInputs[qid] || []).join('\n');
        }

        function renderChips(qid) {
            const wrap = document.querySelector(`.code-input-chips[data-qid="${qid}"]`);
            if (!wrap) return;
            wrap.innerHTML = '';
            const items = codeInputs[qid] || [];
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
                codeInputs[qid] = [];
                codeTranscript[qid] = '';
                codeLastStdout[qid] = undefined;
                renderChips(qid);
                showOutput(qid, OUTPUT_PLACEHOLDER, false);
            });
            wrap.appendChild(clear);
        }

        function showOutput(qid, text, isError, needsInput = false) {
            const box = outputBoxFor(qid);
            if (!box) return;
            const body = box.querySelector('.output-body');
            box.classList.remove('hidden');
            const out = text || (needsInput ? '' : '(tidak ada output)');
            body.textContent = needsInput ? out + INPUT_HINT : out;
            body.classList.toggle('text-red-400', !!isError);
            body.classList.toggle('text-gray-100', !isError);
            if (needsInput) {
                const line = inputLineFor(qid);
                if (line) line.focus();
            }
        }

        function submitInput(qid) {
            const line = inputLineFor(qid);
            if (!line) return;
            const value = line.value;
            if (value === '') return;
            codeInputs[qid] = codeInputs[qid] || [];
            codeInputs[qid].push(value);
            line.value = '';
            renderChips(qid);
            runCode(qid, document.querySelector(`.run-code-btn[data-qid="${qid}"]`), value);
        }

        async function runCode(qid, btn, echoValue) {
            const editorEl = document.querySelector(`.code-editor[data-qid="${qid}"]`);
            if (!editorEl || !window.CBTEditor) return;

            const code = window.CBTEditor.getValue(editorEl);
            const stdin = stdinFor(qid);
            const original = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="ph ph-circle-notch animate-spin"></i> Menjalankan...';
            }
            showOutput(qid, 'Menjalankan kode...', false);

            try {
                const res = await fetch(runUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({ question_id: qid, code: code, stdin: stdin, _token: csrfToken })
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok || data.message) {
                    codeLastStdout[qid] = undefined;
                    showOutput(qid, data.message || ('Gagal menjalankan kode (HTTP ' + res.status + ').'), true);
                    return;
                }

                if (data.compile_output) {
                    const combined = data.output ? data.compile_output + '\n' + data.output : data.compile_output;
                    codeTranscript[qid] = combined;
                    codeLastStdout[qid] = undefined;
                    showOutput(qid, combined, !data.ok);
                    return;
                }

                const stdout = typeof data.stdout === 'string' ? data.stdout : (data.output || '');
                const prev = codeLastStdout[qid];
                let transcript;

                if (echoValue !== undefined && typeof prev === 'string' && stdout.startsWith(prev)) {
                    transcript = (codeTranscript[qid] || '') + echoValue + '\n' + stdout.slice(prev.length);
                } else {
                    transcript = stdout;
                }

                codeTranscript[qid] = transcript;
                codeLastStdout[qid] = stdout;

                const providedInput = (codeInputs[qid] || []).length > 0;
                const needsInput = !!data.needs_input || (!providedInput && looksLikeReadsInput(code));
                showOutput(qid, transcript, !data.ok && !needsInput, needsInput);
            } catch (e) {
                codeLastStdout[qid] = undefined;
                showOutput(qid, 'Tidak dapat menghubungi server. Periksa koneksi Anda.', true);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            }
        }

        document.querySelectorAll('.run-code-btn').forEach((btn) => {
            btn.addEventListener('click', function () {
                runCode(this.dataset.qid, this);
            });
        });

        document.querySelectorAll('.code-input-line').forEach((line) => {
            line.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitInput(this.dataset.qid);
                }
            });
        });

        document.querySelectorAll('.code-input-send').forEach((btn) => {
            btn.addEventListener('click', function () {
                submitInput(this.dataset.qid);
            });
        });

        document.querySelectorAll('.clear-output-btn').forEach((btn) => {
            btn.addEventListener('click', function () {
                const box = outputBoxFor(this.dataset.qid);
                if (!box) return;
                codeTranscript[this.dataset.qid] = '';
                codeLastStdout[this.dataset.qid] = undefined;
                const body = box.querySelector('.output-body');
                body.textContent = OUTPUT_PLACEHOLDER;
                body.classList.remove('text-red-400');
                body.classList.add('text-gray-100');
            });
        });

        // ── Radio change handler ──
        const radios = document.querySelectorAll('.option-radio');
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                const questionDiv = this.closest('.question-card');
                questionDiv.querySelectorAll('.option-label').forEach(label => {
                    label.classList.remove('border-primary', 'bg-primary/5', 'ring-1', 'ring-primary');
                    label.classList.add('border-gray-200');
                });

                if (this.checked) {
                    const label = this.closest('.option-label');
                    label.classList.remove('border-gray-200');
                    label.classList.add('border-primary', 'bg-primary/5', 'ring-1', 'ring-primary');

                    const qid = this.name.match(/\[(.*?)\]/)[1];
                    saveAnswer(qid, this.value);
                }
            });
        });

        // ── Timer ──
        const x = setInterval(function () {
            const elapsedSeconds = Math.floor((Date.now() - localStartTime) / 1000);
            const distance = remainingSeconds - elapsedSeconds;

            if (distance <= 0) {
                clearInterval(x);
                timerElement.innerHTML = "00:00:00";
                alert('Waktu ujian telah habis! Sistem akan otomatis menyimpan jawaban Anda.');
                submitForm();
                return;
            }

            const hours = Math.floor(distance / 3600);
            const minutes = Math.floor((distance % 3600) / 60);
            const seconds = Math.floor(distance % 60);

            timerElement.innerHTML = (hours < 10 ? "0" : "") + hours + ":" +
                                    (minutes < 10 ? "0" : "") + minutes + ":" +
                                    (seconds < 10 ? "0" : "") + seconds;

            if (distance < 5 * 60) {
                timerElement.parentElement.classList.add('animate-pulse', 'text-red-600');
            }
        }, 1000);

        // ── Submit Modal ──
        function updateSummary() {
            const confirmed = document.querySelectorAll('.nav-btn.bg-primary').length;
            const pending = document.querySelectorAll('.nav-btn.bg-amber-400').length;
            const answered = confirmed + pending;
            const total = document.querySelectorAll('.question-card').length;
            document.getElementById('summary-answered').textContent = answered;
            document.getElementById('summary-total').textContent = total;

            const el = document.getElementById('summary-remaining');
            const remaining = total - answered;
            el.textContent = remaining > 0 ? `${remaining} soal belum terjawab` : 'Semua soal sudah terjawab';
            el.className = 'text-sm font-medium pt-1 ' + (remaining > 0 ? 'text-red-600' : 'text-green-600');

            const detail = document.getElementById('summary-answered-detail');
            if (pending > 0) {
                detail.textContent = `${pending} jawaban tersimpan lokal dan akan dikirim otomatis`;
                detail.className = 'text-xs text-amber-600';
            } else {
                detail.textContent = '';
            }
        }

        function confirmSubmit() {
            updateSummary();
            document.getElementById('submit-modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('submit-modal').classList.add('hidden');
        }

        // ── doSubmit with offline handling ──
        function setDoSubmitLoading(loading) {
            const btn = document.getElementById('do-submit-btn');
            if (!btn) return;
            btn.disabled = loading;
            btn.innerHTML = loading
                ? '<i class="ph ph-circle-notch animate-spin text-lg"></i> Mengumpulkan...'
                : '<i class="ph-fill ph-check-circle text-lg"></i> Kumpulkan';
        }

        function flushWithTimeout(ms) {
            let timer;
            const timeout = new Promise((_, reject) => {
                timer = setTimeout(() => reject(new Error('sync_timeout')), ms);
            });
            return Promise.race([flushPendingQueue(), timeout]).finally(() => clearTimeout(timer));
        }

        async function doSubmit() {
            setDoSubmitLoading(true);
            try {
                @if($exam->camera_phone)
                if (typeof phoneCameraPausedState !== 'undefined' && phoneCameraPausedState) {
                    alert('Kamera HP tidak aktif. Aktifkan kembali kamera HP untuk mengumpulkan ujian.');
                    return;
                }
                @endif
                closeModal();

                if (offlineQueue.hasPending()) {
                    if (navigator.onLine) {
                        showSyncModal();
                        try {
                            await flushWithTimeout(15000);
                        } catch (e) {
                            console.warn('Sinkronisasi gagal/timeout, lanjut kumpulkan:', e);
                        } finally {
                            hideSyncModal();
                        }
                    } else {
                        offlineQueue.setPendingSubmit(true);
                        alert('Koneksi terputus. Jawaban akan dikirim otomatis saat koneksi pulih.');
                        return;
                    }
                }

                submitForm();
            } catch (e) {
                console.error('Gagal mengumpulkan ujian:', e);
                alert('Gagal mengumpulkan ujian. Periksa koneksi Anda lalu coba lagi. Jika berlanjut, hubungi pengawas.');
            } finally {
                hideSyncModal();
                setDoSubmitLoading(false);
            }
        }

        document.getElementById('submit-modal')?.addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });

        // ── Tab Switch ──
        let lastTabReport = 0;
        let serverTabSwitches = {{ $session->tab_switches ?? 0 }};
        window.serverTabSwitches = serverTabSwitches;

        function reportTabSwitch() {
            offlineQueue.incrementTabSwitch();

            if (!navigator.onLine) return;
            if (offlineQueue.isFlushing) return;

            const now = Date.now();
            if (now - lastTabReport < 2000) return;
            lastTabReport = now;

            fetch(tabSwitchUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({
                    _token: csrfToken,
                    delta: offlineQueue.getTabSwitches()
                })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                serverTabSwitches = data.tab_switches;
                window.serverTabSwitches = serverTabSwitches;
                offlineQueue.clearTabSwitches();

                if (typeof maxTabSwitches !== 'undefined' && data.exceeded) {
                    alert('Anda telah meninggalkan halaman ujian terlalu banyak kali.\nUjian akan otomatis dikumpulkan.');
                    submitForm();
                } else if (typeof maxTabSwitches !== 'undefined' && serverTabSwitches >= Math.ceil(maxTabSwitches * 0.5) && !tabWarningShown) {
                    tabWarningShown = true;
                    var remaining = maxTabSwitches - serverTabSwitches;
                    var warn = document.createElement('div');
                    warn.id = 'tab-warning';
                    warn.className = 'fixed top-0 left-0 right-0 z-50 bg-red-600 text-white text-center py-3 px-4 text-sm font-medium shadow-lg';
                    warn.innerHTML = '<i class="ph ph-warning-circle text-lg inline-block mr-1.5 align-middle"></i> Peringatan: Anda telah meninggalkan halaman ujian ' + serverTabSwitches + ' kali. Sisa ' + remaining + ' kali sebelum ujian otomatis dikumpulkan.';
                    document.body.prepend(warn);
                    setTimeout(function() {
                        var el = document.getElementById('tab-warning');
                        if (el) el.remove();
                    }, 8000);
                }
            })
            .catch(function(){});
        }

        @if($exam->max_tab_switches)
        const maxTabSwitches = {{ $exam->max_tab_switches }};
        let tabWarningShown = false;

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') reportTabSwitch();
        });
        @endif

        // ── Flush Pending Queue ──
        async function flushPendingQueue() {
            if (offlineQueue.isFlushing || !offlineQueue.hasPending() || !navigator.onLine) return;
            offlineQueue.isFlushing = true;

            const pending = offlineQueue.getPendingAnswers();
            const tabSwitches = offlineQueue.getTabSwitches();
            const wantsSubmit = offlineQueue.hasPendingSubmit();

            if (pending.length > 0 || tabSwitches > 0) {
                try {
                    const res = await fetch(syncUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        _token: csrfToken,
                        answers: pending,
                        tab_switch_delta: tabSwitches
                    })
                    });

                    if (res.ok) {
                        const data = await res.json();
                        offlineQueue.clearAnswers();
                        offlineQueue.clearTabSwitches();
                        serverTabSwitches = data.tab_switches;
                        window.serverTabSwitches = serverTabSwitches;

                        pending.forEach(a => updateNavButton(a.question_id, 'confirmed'));
                        showSaveStatus('saved');

                        if (data.exceeded) {
                            alert('Batas pindah tab terlampaui. Ujian akan otomatis dikumpulkan.');
                            submitForm();
                            return;
                        }
                    } else if (res.status === 419) {
                        // CSRF expired — tidak bisa sync tanpa refresh
                    } else if (res.status === 403) {
                        const errData = await res.json().catch(() => ({}));
                        if (errData.expired) {
                            offlineQueue.clearAll();
                            alert('Waktu ujian telah berakhir. Jawaban yang tersimpan sebelum waktu habis sudah tercatat.');
                            window.location.href = dashboardUrl;
                            return;
                        }
                    }
                } catch (e) {
                    // Still offline or network error — will retry
                }
            }

            if (wantsSubmit && offlineQueue.getPendingCount() === 0) {
                offlineQueue.setPendingSubmit(false);
                submitForm();
            }

            offlineQueue.isFlushing = false;
            updatePendingBadge();
        }

        // ── Connectivity Detection ──
        window.addEventListener('online', () => {
            hideOfflineBanner();
            setTimeout(() => flushPendingQueue(), 1000);
        });
        window.addEventListener('offline', () => showOfflineBanner());

        // Initial online check
        if (!navigator.onLine) showOfflineBanner();

        // Periodic retry every 15 seconds
        setInterval(() => {
            if (navigator.onLine && offlineQueue.hasPending()) {
                flushPendingQueue();
            }
        }, 15000);

        // Check for pending from previous session on page load
        if (offlineQueue.hasPending() && navigator.onLine) {
            setTimeout(() => flushPendingQueue(), 2000);
        }

        // ── Restore nav button states on page load ──
        (function restoreNavStates() {
            const pendingAnswers = offlineQueue.getPendingAnswers();
            pendingAnswers.forEach(a => updateNavButton(a.question_id, 'pending'));
        })();

        @if($exam->camera_proctoring)
        // ── Camera Proctoring ──
        let proctorLaptop = null;
        @if($exam->camera_phone)
        let phoneCameraStatusTimer = null;
        let phoneCameraPausedState = false;

        function setPhoneCameraPaused(paused) {
            phoneCameraPausedState = !!paused;
            const overlay = document.getElementById('phone-camera-paused-overlay');
            if (!overlay) return;
            overlay.classList.toggle('hidden', !paused);
            overlay.classList.toggle('flex', paused);
        }

        async function checkPhoneCameraStatus() {
            try {
                const response = await fetch('{{ route("proctor.pair_status", $exam) }}', {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store',
                });
                if (!response.ok) return;
                const data = await response.json();
                setPhoneCameraPaused(!data.paired || data.camera_active === false);
            } catch (error) {
                console.warn('Phone camera status check failed', error);
            }
        }
        @endif

        (async function initCameraProctoring() {
            try {
                // Import from built asset
                const module = await import('{{ Vite::asset('resources/js/proctor-laptop.js') }}');
                const { ProctorLaptop } = module;

                proctorLaptop = new ProctorLaptop({
                    sessionId: {{ $session->id }},
                    eventUrl: '{{ route("proctor.event") }}',
                    csrfToken: csrfToken,
                });

                const granted = await proctorLaptop.init();
                if (!granted) {
                    alert('Gagal mengakses kamera. Silakan refresh halaman dan izinkan kembali.');
                    window.location.href = '{{ route("student.exams.show", $exam) }}';
                    return;
                }
                @if($exam->camera_phone)
                await checkPhoneCameraStatus();
                phoneCameraStatusTimer = setInterval(checkPhoneCameraStatus, 2000);
                @endif

            } catch (e) {
                console.error('Failed to init camera proctor:', e);
                    alert('Proctor kamera gagal dimulai. Ujian tidak dapat dilanjutkan tanpa kamera.');
                    window.location.href = '{{ route("student.exams.show", $exam) }}';
            }
        })();
        @endif

        @if($exam->require_fullscreen)
        // ── Fullscreen Gate + Exit Detection ──
        var fsExited = false;
        var fsGate = document.getElementById('attempt-fs-overlay');

        function enterFs() {
            var el = document.documentElement;
            if (el.requestFullscreen) { el.requestFullscreen(); }
            else if (el.webkitRequestFullscreen) { el.webkitRequestFullscreen(); }
            else if (el.msRequestFullscreen) { el.msRequestFullscreen(); }
        }

        function handleFsChange() {
            var inFs = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
            if (inFs) {
                if (fsGate) fsGate.classList.add('hidden');
                fsExited = false;
            } else {
                if (!fsExited) {
                    fsExited = true;
                    reportTabSwitch();
                }
                if (fsGate) fsGate.classList.remove('hidden');
            }
        }
        document.addEventListener('fullscreenchange', handleFsChange);
        document.addEventListener('webkitfullscreenchange', handleFsChange);
        document.addEventListener('msfullscreenchange', handleFsChange);

        if (fsGate && !document.fullscreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
            fsGate.classList.remove('hidden');
        }
        @endif
    </script>
</body>
</html>
