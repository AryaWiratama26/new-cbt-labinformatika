@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto w-full px-4 sm:px-6 py-6 sm:py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div>
            <a href="{{ route('admin.courses.modules.index', $course) }}" class="text-sm text-gray-500 hover:text-primary inline-flex items-center gap-1 mb-1">
                <i class="ph ph-arrow-left text-sm"></i> {{ $course->name }}
            </a>
            @if($module->module_number)
                <p class="text-sm font-semibold text-secondary">{{ $module->module_number }}</p>
            @endif
            <h2 class="text-3xl font-bold text-gray-900">{{ $module->name }}</h2>
        </div>
        <div class="grid grid-cols-2 gap-2 md:flex md:gap-3 w-full md:w-auto">
            <a href="{{ route('admin.template_questions') }}" class="inline-flex items-center justify-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-download-simple"></i> Template CSV
            </a>
            <a href="{{ route('admin.template_questions.docx') }}" class="inline-flex items-center justify-center gap-2 bg-white border border-primary/20 hover:bg-[#e8eaf5] text-primary py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-download-simple"></i> Template Word
            </a>
            <button type="button" onclick="openAiGenerator()" class="inline-flex items-center justify-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-black py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <img src="/images/ChatGPT-Logo.png" alt="AI" class="h-5 w-auto inline-block"> Generate dengan AI
            </button>
            <a href="{{ route('admin.courses.modules.questions.create', [$course, $module]) }}" class="inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-plus"></i> Tambah Manual
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i> {{ session('success') }}
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Import Soal via CSV</h3>
            <p class="text-sm text-gray-500 mb-4">Upload CSV soal beserta ZIP gambar (opsional).</p>
            <form action="{{ route('admin.courses.modules.import_questions', [$course, $module]) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1 block">File CSV Soal *</label>
                    <input type="file" name="csv_file" accept=".csv" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#e8eaf5] file:text-primary hover:file:bg-[#dde0f0] cursor-pointer border border-gray-200 rounded-xl p-2">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1 block">ZIP Gambar (Opsional)</label>
                    <input type="file" name="images_zip" accept=".zip" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-50 file:text-gray-700 cursor-pointer border border-gray-200 rounded-xl p-2">
                </div>
                <button type="submit" class="w-full bg-secondary hover:bg-secondary-hover text-white py-3 rounded-xl font-medium transition-colors flex justify-center items-center gap-2">
                    <i class="ph ph-upload-simple"></i> Import Soal
                </button>
            </form>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Import Soal via Word</h3>
            <p class="text-sm text-gray-500 mb-4">Upload file .docx dengan format soal yang sudah ditentukan.</p>
            <form action="{{ route('admin.courses.modules.import_questions.docx', [$course, $module]) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1 block">File .docx Soal *</label>
                    <input type="file" name="docx_file" accept=".docx" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#e8eaf5] file:text-primary hover:file:bg-[#dde0f0] cursor-pointer border border-gray-200 rounded-xl p-2">
                </div>
                <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white py-3 rounded-xl font-medium transition-colors flex justify-center items-center gap-2">
                    <i class="ph ph-upload-simple"></i> Import dari Word
                </button>
            </form>
        </div>

        <div class="md:col-span-2">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Bank Soal <span class="text-sm font-normal text-gray-500">({{ $questions->total() }} soal)</span></h3>
                    <form method="GET" class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari soal..." class="w-full sm:w-48 px-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none bg-gray-50/50">
                        <div class="flex gap-2">
                            <select name="category" class="flex-1 sm:flex-none px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                                <option value="">Semua</option>
                                <option value="mudah" {{ request('category') == 'mudah' ? 'selected' : '' }}>Mudah</option>
                                <option value="sedang" {{ request('category') == 'sedang' ? 'selected' : '' }}>Sedang</option>
                                <option value="sulit" {{ request('category') == 'sulit' ? 'selected' : '' }}>Sulit</option>
                            </select>
                            <button type="submit" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm font-medium transition-colors"><i class="ph ph-magnifying-glass"></i></button>
                            @if(request('search') || request('category'))
                                <a href="{{ route('admin.courses.modules.show', [$course, $module]) }}" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm font-medium transition-colors flex items-center"><i class="ph ph-x"></i></a>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="space-y-5">
                    @forelse($questions as $question)
                        <div class="p-5 border border-gray-100 rounded-2xl bg-gray-50/50">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center bg-secondary text-white h-8 w-8 rounded-full font-bold text-sm flex-shrink-0">
                                        {{ $questions->firstItem() + $loop->index }}
                                    </span>
                                    @if($question->category)
                                        <span class="px-2 py-0.5 text-xs rounded-full font-medium
                                            {{ $question->category === 'mudah' ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $question->category === 'sedang' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $question->category === 'sulit' ? 'bg-red-100 text-red-700' : '' }}">
                                            {{ ucfirst($question->category) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex gap-1">
                                    <a href="{{ route('admin.courses.modules.questions.edit', [$course, $module, $question]) }}" class="text-secondary hover:text-secondary-hover p-1.5 rounded-lg hover:bg-[#eeedf7] transition-colors" title="Edit">
                                        <i class="ph ph-pencil-simple text-lg"></i>
                                    </a>
                                    <form action="{{ route('admin.courses.modules.questions.duplicate', [$course, $module, $question]) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-primary hover:text-primary-hover p-1.5 rounded-lg hover:bg-[#e8eaf5] transition-colors" title="Duplikat">
                                            <i class="ph ph-copy text-lg"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.courses.modules.questions.destroy', [$course, $module, $question]) }}" method="POST" onsubmit="return confirm('Hapus soal ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 p-1.5 rounded-lg hover:bg-red-50 transition-colors" title="Hapus">
                                            <i class="ph ph-trash text-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            @if($question->image)
                                <div class="mb-3">
                                    <img src="{{ asset('storage/' . $question->image) }}" alt="Gambar Soal" class="max-h-40 max-w-full h-auto rounded-lg border border-gray-200 object-contain bg-white p-1">
                                </div>
                            @endif

                            <p class="text-gray-900 font-medium mb-3 whitespace-pre-wrap break-words">{{ $question->content }}</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($question->options as $option)
                                    <div class="px-3 py-2 rounded-xl border {{ $option->is_correct ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200' }} flex gap-2 items-center min-w-0">
                                        @if($option->is_correct)
                                            <i class="ph-fill ph-check-circle text-green-500 flex-shrink-0"></i>
                                        @else
                                            <i class="ph ph-circle text-gray-300 flex-shrink-0"></i>
                                        @endif
                                        <span class="text-sm {{ $option->is_correct ? 'text-green-800 font-semibold' : 'text-gray-600' }} break-words">{{ $option->content }}</span>
                                    </div>
                                @endforeach
                            </div>

                            @if($question->explanation)
                                <div class="mt-3 p-3 bg-[#e8eaf5] border border-primary/10 rounded-xl">
                                    <p class="text-xs font-semibold text-primary mb-1"><i class="ph ph-info"></i> Pembahasan:</p>
                                    <p class="text-sm text-gray-700 break-words whitespace-pre-wrap">{{ $question->explanation }}</p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-12 border-2 border-dashed border-gray-200 rounded-2xl">
                            <div class="inline-flex h-14 w-14 bg-gray-100 rounded-full items-center justify-center text-gray-400 mb-3">
                                <i class="ph ph-list-dashes text-2xl"></i>
                            </div>
                            <p class="text-gray-600 font-medium">Belum ada soal</p>
                            <p class="text-sm text-gray-400">Import via CSV atau tambah soal manual.</p>
                        </div>
                    @endforelse

                    <div class="pt-4 overflow-x-auto">
                        <div class="flex justify-center">{{ $questions->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- AI Generator Modal --}}
<div id="ai-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" style="backdrop-filter:blur(4px);">
    <div class="bg-white rounded-[2rem] shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-5 sm:p-8">
        <div class="flex items-center gap-3 mb-6">
            <div class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center">
                <img src="/images/ChatGPT-Logo.png" alt="AI" class="h-5 w-auto inline-block">
            </div>
            <div class="flex-1">
                <h3 class="text-xl font-bold text-gray-900">Generate Soal dengan AI</h3>
                <p class="text-xs text-gray-500">Groq AI membuat soal berdasarkan topik yang dimasukkan</p>
            </div>
            <button onclick="closeAiGenerator()" class="h-8 w-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition-colors">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>

        <div id="ai-modal-error" class="hidden mb-4 p-3 rounded-xl bg-red-50 text-red-700 text-sm flex items-center gap-2"></div>
        <div id="ai-modal-success" class="hidden mb-4 p-3 rounded-xl bg-green-50 text-green-700 text-sm flex items-center gap-2"></div>

        <div id="ai-input-section" class="space-y-5">
            <div>
                <label class="block text-sm font-bold text-gray-900 mb-2">Topik <span class="text-red-500">*</span></label>
                <input type="text" id="ai-topic" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" placeholder="Contoh: Hukum Newton, Sistem Pernapasan, Teori Ekonomi..." maxlength="255">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-900 mb-2">Tingkat Kesulitan <span class="text-red-500">*</span></label>
                    <select id="ai-difficulty" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none bg-white">
                        <option value="mudah">Mudah</option>
                        <option value="sedang" selected>Sedang</option>
                        <option value="sulit">Sulit</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-900 mb-2">Jumlah Soal <span class="text-red-500">*</span></label>
                    <input type="number" id="ai-count" value="1" min="1" max="10" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                    <p class="text-xs text-gray-400 mt-1">Maksimal 10 soal per generate</p>
                </div>
            </div>
        </div>

        <div id="ai-modal-loading" class="hidden text-center py-8">
            <div class="inline-flex items-center justify-center h-14 w-14 rounded-full bg-gradient-to-br from-purple-500/10 to-primary/10 text-primary mb-4 animate-pulse">
                <i class="ph ph-spinner-gap text-2xl animate-spin"></i>
            </div>
            <h4 class="font-bold text-gray-900 mb-1">Membuat Soal...</h4>
            <p class="text-xs text-gray-500">Groq AI sedang menyusun soal sesuai topik yang dimasukkan</p>
        </div>

        <div id="ai-results" class="hidden space-y-4 mt-2"></div>

        <div class="flex gap-3 mt-6" id="ai-modal-actions">
            <button type="button" onclick="closeAiGenerator()" class="flex-1 py-3 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-xl font-medium transition-colors text-sm">Batal</button>
            <button type="button" onclick="generateQuestions()" class="flex-1 py-3 bg-white border border-gray-300 hover:bg-gray-50 text-black rounded-xl font-medium transition-colors text-sm flex items-center justify-center gap-2">
                <img src="/images/ChatGPT-Logo.png" alt="AI" class="h-5 w-auto inline-block"> Generate
            </button>
        </div>
    </div>
</div>

<script>
function previewImage(input, containerId) {
    const container = document.getElementById(containerId);
    const img = container.querySelector('img');
    if (input.files && input.files[0]) {
        img.src = URL.createObjectURL(input.files[0]);
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
        img.src = '';
    }
}

// ── AI Question Generator ──
const aiGenerateUrl = "{{ route('admin.courses.modules.questions.generate', [$course, $module]) }}";
const aiSaveUrl = "{{ route('admin.courses.modules.questions.save_generated', [$course, $module]) }}";
const csrfToken = document.querySelector('input[name="_token"]').value;
let generatedQuestions = [];

function openAiGenerator() {
    document.getElementById('ai-modal').classList.remove('hidden');
    document.getElementById('ai-topic').focus();
}

function closeAiGenerator() {
    document.getElementById('ai-modal').classList.add('hidden');
    document.getElementById('ai-modal-error').classList.add('hidden');
    document.getElementById('ai-modal-success').classList.add('hidden');
    document.getElementById('ai-results').classList.add('hidden');
    document.getElementById('ai-results').innerHTML = '';
    document.getElementById('ai-input-section').classList.remove('hidden');
    resetActions();
    generatedQuestions = [];
}

function resetActions() {
    document.getElementById('ai-modal-actions').innerHTML =
        '<button type="button" onclick="closeAiGenerator()" class="flex-1 py-3 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-xl font-medium transition-colors text-sm">Batal</button>' +
        '<button type="button" onclick="generateQuestions()" class="flex-1 py-3 bg-white border border-gray-300 hover:bg-gray-50 text-black rounded-xl font-medium transition-colors text-sm flex items-center justify-center gap-2">' +
        '<img src=\"/images/ChatGPT-Logo.png\" alt=\"AI\" class=\"h-5 w-auto inline-block\"> Generate</button>';
}

async function generateQuestions() {
    const topic = document.getElementById('ai-topic').value.trim();
    const difficulty = document.getElementById('ai-difficulty').value;
    const count = parseInt(document.getElementById('ai-count').value) || 1;
    const errorEl = document.getElementById('ai-modal-error');
    const loadingEl = document.getElementById('ai-modal-loading');
    const actionsEl = document.getElementById('ai-modal-actions');
    const inputSection = document.getElementById('ai-input-section');

    if (!topic) {
        errorEl.className = 'mb-4 p-3 rounded-xl bg-red-50 text-red-700 text-sm flex items-center gap-2';
        errorEl.innerHTML = '<i class="ph ph-warning-circle text-lg"></i> Masukkan topik terlebih dahulu.';
        return;
    }

    errorEl.classList.add('hidden');
    inputSection.classList.add('hidden');
    loadingEl.classList.remove('hidden');
    actionsEl.classList.add('hidden');

    try {
        const res = await fetch(aiGenerateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ _token: csrfToken, topic, difficulty, count })
        });

        const data = await res.json();

        loadingEl.classList.add('hidden');
        actionsEl.classList.remove('hidden');

        if (!data.success) {
            errorEl.className = 'mb-4 p-3 rounded-xl bg-red-50 text-red-700 text-sm flex items-center gap-2';
            errorEl.innerHTML = '<i class="ph ph-warning-circle text-lg"></i> ' + (data.message || 'Gagal menghasilkan soal.');
            inputSection.classList.remove('hidden');
            return;
        }

        generatedQuestions = data.questions || [];
        renderResults(generatedQuestions);
    } catch (e) {
        loadingEl.classList.add('hidden');
        actionsEl.classList.remove('hidden');
        inputSection.classList.remove('hidden');
        errorEl.className = 'mb-4 p-3 rounded-xl bg-red-50 text-red-700 text-sm flex items-center gap-2';
        errorEl.innerHTML = '<i class="ph ph-warning-circle text-lg"></i> Terjadi kesalahan jaringan. Coba lagi.';
    }
}

function renderResults(questions) {
    const resultsEl = document.getElementById('ai-results');
    const actionsEl = document.getElementById('ai-modal-actions');
    resultsEl.innerHTML = '';

    if (questions.length === 0) {
        resultsEl.classList.add('hidden');
        return;
    }

    let html = '<div class="flex flex-wrap items-center justify-between gap-2 mb-2">' +
        '<h4 class="font-bold text-gray-900">Hasil Generate (' + questions.length + ' soal)</h4>' +
        '<button onclick="saveAllQuestions()" class="text-sm bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl font-medium transition-colors flex items-center gap-1">' +
        '<i class="ph ph-database text-base"></i> Tambah Semua</button></div>';

    questions.forEach((q, i) => {
        const correctIdx = q.options.findIndex(o => o.is_correct);
        html += '<div class="p-4 border border-gray-200 rounded-2xl bg-gray-50" id="ai-q-' + i + '">';
        html += '<div class="flex justify-between items-start mb-2">';
        html += '<span class="inline-flex items-center justify-center bg-primary text-white h-7 w-7 rounded-full font-bold text-xs flex-shrink-0">' + (i + 1) + '</span>';
        html += '<button onclick="saveQuestion(' + i + ')" class="text-sm bg-primary hover:bg-primary-hover text-white px-3 py-1.5 rounded-lg font-medium transition-colors flex items-center gap-1">' +
            '<i class="ph ph-plus text-sm"></i> Tambah</button>';
        html += '</div>';
        html += '<p class="question-content text-gray-900 font-medium text-sm mb-2 whitespace-pre-wrap break-words">' + renderCodeBlocks(escHtml(q.content)) + '</p>';
        html += '<div class="space-y-1 mb-2">';
        q.options.forEach((opt, oi) => {
            const correct = oi === correctIdx;
            html += '<div class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm min-w-0 ' +
                (correct ? 'bg-green-50 border border-green-200' : 'bg-white border border-gray-200') + '">';
            html += correct ? '<i class="ph-fill ph-check-circle text-green-500 flex-shrink-0"></i>' : '<i class="ph ph-circle text-gray-300 flex-shrink-0"></i>';
            html += '<span class="break-words ' + (correct ? 'text-green-800 font-semibold' : 'text-gray-600') + '">' + renderCodeBlocks(escHtml(opt.content)) + '</span>';
            html += '</div>';
        });
        html += '</div>';
        if (q.explanation) {
            html += '<div class="p-2.5 rounded-xl bg-[#e8eaf5] border border-primary/10">';
            html += '<p class="text-xs font-semibold text-primary mb-0.5"><i class="ph ph-info"></i> Pembahasan:</p>';
            html += '<p class="text-xs text-gray-700 break-words whitespace-pre-wrap">' + renderCodeBlocks(escHtml(q.explanation)) + '</p></div>';
        }
        html += '</div>';
    });

    resultsEl.innerHTML = html;

    if (typeof renderMathInElement === 'function') {
        renderMathInElement(resultsEl, {
            delimiters: [
                {left: '\\[', right: '\\]', display: true},
                {left: '\\(', right: '\\)', display: false},
                {left: '$$', right: '$$', display: true},
                {left: '$', right: '$', display: false},
            ],
            throwOnError: false,
        });
    }
    if (typeof Prism !== 'undefined') {
        Prism.highlightAllUnder(resultsEl);
    }

    resultsEl.classList.remove('hidden');

    actionsEl.innerHTML =
        '<button type="button" onclick="closeAiGenerator()" class="flex-1 py-3 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-xl font-medium transition-colors text-sm">Tutup</button>' +
        '<button type="button" onclick="resetGenerator()" class="flex-1 py-3 bg-white border border-gray-300 hover:bg-gray-50 text-black rounded-xl font-medium transition-colors text-sm flex items-center justify-center gap-2">' +
        '<img src=\"/images/ChatGPT-Logo.png\" alt=\"AI\" class=\"h-5 w-auto inline-block\"> Generate Lagi</button>';
}

function resetGenerator() {
    document.getElementById('ai-results').classList.add('hidden');
    document.getElementById('ai-results').innerHTML = '';
    document.getElementById('ai-input-section').classList.remove('hidden');
    document.getElementById('ai-modal-error').classList.add('hidden');
    document.getElementById('ai-modal-success').classList.add('hidden');
    resetActions();
    generatedQuestions = [];
}

async function saveQuestion(index) {
    const q = generatedQuestions[index];
    if (!q) return;

    const card = document.getElementById('ai-q-' + index);
    const btn = card ? card.querySelector('button') : null;
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner-gap animate-spin text-sm"></i>'; }

    try {
        const res = await fetch(aiSaveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({
                _token: csrfToken, content: q.content, options: q.options,
                explanation: q.explanation || '', category: q.category || ''
            })
        });
        const data = await res.json();

        if (data.success) {
            generatedQuestions.splice(index, 1);
            renderResults(generatedQuestions);
            showSuccess('Soal berhasil ditambahkan.');
        } else {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ph ph-plus text-sm"></i> Tambah'; }
            showError(data.message || 'Gagal menyimpan soal.');
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ph ph-plus text-sm"></i> Tambah'; }
        showError('Terjadi kesalahan jaringan. Coba lagi.');
    }
}

async function saveAllQuestions() {
    const total = generatedQuestions.length;
    if (total === 0) return;

    const btn = document.querySelector('#ai-results button');
    if (btn) { btn.disabled = true; btn.innerHTML = 'Menyimpan...'; }

    let saved = 0;
    for (const q of generatedQuestions) {
        try {
            const res = await fetch(aiSaveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({
                    _token: csrfToken, content: q.content, options: q.options,
                    explanation: q.explanation || '', category: q.category || ''
                })
            });
            const data = await res.json();
            if (data.success) saved++;
        } catch (e) {}
    }

    showSuccess(saved + ' dari ' + total + ' soal berhasil ditambahkan.');
    generatedQuestions = [];
    document.getElementById('ai-results').classList.add('hidden');
    document.getElementById('ai-results').innerHTML = '';
    document.getElementById('ai-input-section').classList.remove('hidden');
    resetActions();

    setTimeout(() => location.reload(), 1500);
}

function showSuccess(msg) {
    const el = document.getElementById('ai-modal-success');
    el.className = 'mb-4 p-3 rounded-xl bg-green-50 text-green-700 text-sm flex items-center gap-2';
    el.innerHTML = '<i class="ph ph-check-circle text-lg"></i> ' + msg;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
}

function showError(msg) {
    const el = document.getElementById('ai-modal-error');
    el.className = 'mb-4 p-3 rounded-xl bg-red-50 text-red-700 text-sm flex items-center gap-2';
    el.innerHTML = '<i class="ph ph-warning-circle text-lg"></i> ' + msg;
    el.classList.remove('hidden');
}

function escHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
@endsection
