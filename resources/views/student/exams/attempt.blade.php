<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian CBT - {{ $exam->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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

    <!-- BUG #17 fix: save status indicator bar -->
    <div id="save-status-bar" class="hidden fixed top-16 left-0 right-0 z-40 text-center text-xs font-medium py-1.5 transition-all duration-300"></div>
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

    <!-- Main Content -->
    <main class="flex-grow flex overflow-hidden max-w-7xl mx-auto w-full" id="attempt-main">
        
        <!-- Questions Area -->
        <div class="flex-grow overflow-y-auto p-6 md:p-8 scroll-smooth" id="questions-container">
            <form id="exam-form" action="{{ route('student.exams.submit', $exam) }}" method="POST">
                @csrf
                
                <div class="space-y-12 max-w-3xl mx-auto pb-24">
                    @foreach($questions as $index => $question)
                        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 question-card" id="q-{{ $question->id }}">
                            <div class="flex gap-4 mb-6">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-lg border border-primary/20">
                                    {{ $index + 1 }}
                                </div>
                                <div class="flex-grow pt-1.5">
                                    <p class="text-gray-900 text-lg font-medium leading-relaxed whitespace-pre-wrap">{{ $question->content }}</p>
                                </div>
                            </div>

                            @if($question->image)
                                <div class="ml-14 mb-6">
                                    <img src="{{ asset('storage/' . $question->image) }}" alt="Gambar Soal" class="max-h-64 rounded-xl border border-gray-200 p-1 bg-gray-50">
                                </div>
                            @endif

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
                        $isAnswered = isset($existingAnswers[$question->id]);
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
                <button type="button" onclick="doSubmit()" class="flex-1 py-3.5 bg-primary hover:bg-primary-hover text-white rounded-xl font-bold transition-colors flex items-center justify-center gap-2">
                    <i class="ph-fill ph-check-circle text-lg"></i> Kumpulkan
                </button>
            </div>
        </div>
    </div>

    <!-- Logic Script -->
    <script>
        // BUG #18 fix: Gunakan sisa waktu dari server (detik) dan performance.now() 
        // untuk mencegah manipulasi jam sistem (OS clock) oleh peserta ujian.
        const remainingSeconds = {{ max(0, $endTime->diffInSeconds(now())) }};
        const localStartTime = performance.now();

        const timerElement = document.getElementById('countdown-timer');
        const form = document.getElementById('exam-form');
        const csrfToken = document.querySelector('input[name="_token"]').value;
        const saveUrl = "{{ route('student.exams.save_answer', $exam) }}";
        let saving = false;
        let pendingSave = null;

        async function saveAnswer(questionId, optionId) {
            if (saving) {
                pendingSave = { questionId, optionId };
                return;
            }
            saving = true;

            // BUG #17 fix: tampilkan status "Menyimpan..."
            showSaveStatus('saving');

            try {
                const payload = { question_id: questionId, option_id: optionId, _token: csrfToken };
                const res = await fetch(saveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (res.ok) {
                    showSaveStatus('saved');
                } else {
                    showSaveStatus('error');
                }
            } catch (e) {
                // BUG #17 fix: tampilkan status "Gagal!" bukan silent
                showSaveStatus('error');
            } finally {
                saving = false;
                if (pendingSave) {
                    const next = pendingSave;
                    pendingSave = null;
                    saveAnswer(next.questionId, next.optionId);
                }
            }
        }

        let saveStatusTimer = null;
        function showSaveStatus(type) {
            const bar = document.getElementById('save-status-bar');
            if (!bar) return;
            clearTimeout(saveStatusTimer);
            bar.className = 'fixed top-16 left-0 right-0 z-40 text-center text-xs font-medium py-1.5 transition-all duration-300';
            if (type === 'saving') {
                bar.className += ' bg-blue-50 text-blue-700 border-b border-blue-200';
                bar.innerHTML = '<i class="ph ph-spinner animate-spin inline-block mr-1"></i> Menyimpan jawaban...';
                bar.classList.remove('hidden');
            } else if (type === 'saved') {
                bar.className += ' bg-green-50 text-green-700 border-b border-green-200';
                bar.innerHTML = '<i class="ph ph-check-circle inline-block mr-1"></i> Jawaban tersimpan';
                bar.classList.remove('hidden');
                saveStatusTimer = setTimeout(() => bar.classList.add('hidden'), 2000);
            } else if (type === 'error') {
                bar.className += ' bg-red-50 text-red-700 border-b border-red-200';
                bar.innerHTML = '<i class="ph ph-warning-circle inline-block mr-1"></i> Gagal menyimpan! Periksa koneksi internet Anda.';
                bar.classList.remove('hidden');
                saveStatusTimer = setTimeout(() => bar.classList.add('hidden'), 5000);
            }
        }

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

                    const navBtn = document.querySelector(`.nav-btn[data-qid="${qid}"]`);
                    if (navBtn) {
                        navBtn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
                        navBtn.classList.add('bg-primary', 'text-white', 'border-primary');
                    }
                }
            });
        });

        const x = setInterval(function () {
            const elapsedSeconds = Math.floor((performance.now() - localStartTime) / 1000);
            const distance = remainingSeconds - elapsedSeconds;

            if (distance <= 0) {
                clearInterval(x);
                timerElement.innerHTML = "00:00:00";
                alert('Waktu ujian telah habis! Sistem akan otomatis menyimpan jawaban Anda.');
                form.submit();
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

        function updateSummary() {
            const answered = document.querySelectorAll('.nav-btn.bg-primary').length;
            const total = document.querySelectorAll('.question-card').length;
            document.getElementById('summary-answered').textContent = answered;
            document.getElementById('summary-total').textContent = total;

            const el = document.getElementById('summary-remaining');
            const remaining = total - answered;
            el.textContent = remaining > 0 ? `${remaining} soal belum terjawab` : 'Semua soal sudah terjawab';
            el.className = 'text-sm font-medium pt-1 ' + (remaining > 0 ? 'text-red-600' : 'text-green-600');
        }

        function confirmSubmit() {
            updateSummary();
            document.getElementById('submit-modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('submit-modal').classList.add('hidden');
        }

        function doSubmit() {
            closeModal();
            form.submit();
        }

        document.getElementById('submit-modal')?.addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });

        // ── Tab Switch Detection (core) ──
        const tabSwitchUrl = "{{ route('student.exams.tab_switch', $exam) }}";
        let lastTabReport = 0;

        function reportTabSwitch() {
            var now = Date.now();
            if (now - lastTabReport < 2000) return;
            lastTabReport = now;

            fetch(tabSwitchUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ _token: csrfToken })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                if (typeof tabSwitchCount !== 'undefined') {
                    tabSwitchCount = data.tab_switches;
                }
                if (typeof maxTabSwitches !== 'undefined' && data.exceeded) {
                    alert('Anda telah meninggalkan halaman ujian terlalu banyak kali.\nUjian akan otomatis dikumpulkan.');
                    form.submit();
                } else if (typeof maxTabSwitches !== 'undefined' && tabSwitchCount >= Math.ceil(maxTabSwitches * 0.5) && !tabWarningShown) {
                    tabWarningShown = true;
                    var remaining = maxTabSwitches - tabSwitchCount;
                    var warn = document.createElement('div');
                    warn.id = 'tab-warning';
                    warn.className = 'fixed top-0 left-0 right-0 z-50 bg-red-600 text-white text-center py-3 px-4 text-sm font-medium shadow-lg';
                    warn.innerHTML = '<i class=\"ph ph-warning-circle text-lg inline-block mr-1.5 align-middle\"></i> Peringatan: Anda telah meninggalkan halaman ujian ' + tabSwitchCount + ' kali. Sisa ' + remaining + ' kali sebelum ujian otomatis dikumpulkan.';
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
        // ── Tab Switch Trigger ──
        const maxTabSwitches = {{ $exam->max_tab_switches }};
        let tabSwitchCount = {{ $session->tab_switches ?? 0 }};
        let tabWarningShown = false;

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') reportTabSwitch();
        });
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
