@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
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
        <div class="flex gap-3">
            <a href="{{ route('admin.template_questions') }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-download-simple"></i> Template CSV
            </a>
            <a href="{{ route('admin.template_questions.docx') }}" class="inline-flex items-center gap-2 bg-white border border-primary/20 hover:bg-[#e8eaf5] text-primary py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-download-simple"></i> Template Word
            </a>
            <a href="{{ route('admin.courses.modules.questions.create', [$course, $module]) }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
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
                    <form method="GET" class="flex gap-2">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari soal..." class="px-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none bg-gray-50/50 w-48">
                        <select name="category" class="px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                            <option value="">Semua</option>
                            <option value="mudah" {{ request('category') == 'mudah' ? 'selected' : '' }}>Mudah</option>
                            <option value="sedang" {{ request('category') == 'sedang' ? 'selected' : '' }}>Sedang</option>
                            <option value="sulit" {{ request('category') == 'sulit' ? 'selected' : '' }}>Sulit</option>
                        </select>
                        <button type="submit" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm font-medium transition-colors"><i class="ph ph-magnifying-glass"></i></button>
                        @if(request('search') || request('category'))
                            <a href="{{ route('admin.courses.modules.show', [$course, $module]) }}" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm font-medium transition-colors flex items-center"><i class="ph ph-x"></i></a>
                        @endif
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
                                    <img src="{{ asset('storage/' . $question->image) }}" alt="Gambar Soal" class="max-h-40 rounded-lg border border-gray-200 object-contain bg-white p-1">
                                </div>
                            @endif

                            <p class="text-gray-900 font-medium mb-3 whitespace-pre-wrap">{{ $question->content }}</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($question->options as $option)
                                    <div class="px-3 py-2 rounded-xl border {{ $option->is_correct ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200' }} flex gap-2 items-center">
                                        @if($option->is_correct)
                                            <i class="ph-fill ph-check-circle text-green-500 flex-shrink-0"></i>
                                        @else
                                            <i class="ph ph-circle text-gray-300 flex-shrink-0"></i>
                                        @endif
                                        <span class="text-sm {{ $option->is_correct ? 'text-green-800 font-semibold' : 'text-gray-600' }}">{{ $option->content }}</span>
                                    </div>
                                @endforeach
                            </div>

                            @if($question->explanation)
                                <div class="mt-3 p-3 bg-[#e8eaf5] border border-primary/10 rounded-xl">
                                    <p class="text-xs font-semibold text-primary mb-1"><i class="ph ph-info"></i> Pembahasan:</p>
                                    <p class="text-sm text-gray-700">{{ $question->explanation }}</p>
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

                    <div class="pt-4">
                        {{ $questions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
