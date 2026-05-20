@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.exams.index') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-3xl font-bold text-gray-900 mb-1">Detail Jadwal Ujian</h2>
                <p class="text-gray-500">Pratinjau soal dari modul yang terhubung.</p>
            </div>
        </div>
    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl"></i> {{ session('error') }}
        </div>
    @endif
        <div class="flex gap-3 flex-wrap">
            <a href="{{ route('admin.exams.monitor', $exam) }}" class="inline-flex items-center gap-2 bg-white border border-primary/20 hover:bg-[#e8eaf5] text-primary py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-eye text-lg"></i> Monitor
            </a>
            <a href="{{ route('admin.exams.results', $exam) }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-chart-bar text-lg"></i> Lihat Hasil
            </a>
            <a href="{{ route('admin.exams.pdf', $exam) }}" class="inline-flex items-center gap-2 bg-white border border-primary/20 hover:bg-[#e8eaf5] text-primary py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-file-pdf text-lg"></i> PDF
            </a>
            <button type="button" onclick="document.getElementById('duplicate-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 bg-white border border-secondary/20 hover:bg-[#eeedf7] text-secondary py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-copy text-lg"></i> Duplikat
            </button>
            <a href="{{ route('admin.exams.edit', $exam) }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-pencil-simple text-lg"></i> Edit Jadwal
            </a>
        </div>
    </div>

    @if(session('duplicate_errors'))
        <div class="mb-6 p-4 rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-800">
            <div class="flex items-start gap-3">
                <i class="ph ph-warning-circle text-xl flex-shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-medium">Beberapa kelas gagal diduplikat:</p>
                    <ul class="list-disc list-inside text-sm mt-1 space-y-0.5">
                        @foreach(session('duplicate_errors') as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- Duplicate Modal -->
    <div id="duplicate-modal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" style="backdrop-filter:blur(4px);">
        <div class="bg-white rounded-[2rem] shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col">
            <form method="POST" action="{{ route('admin.exams.duplicate', $exam) }}" class="flex flex-col max-h-[90vh]">
                @csrf
                <div class="p-6 pb-4 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Duplikat Ujian</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Pilih kelas dan atur jadwal untuk {{ $exam->title }}</p>
                    </div>
                    <button type="button" onclick="this.closest('#duplicate-modal').classList.add('hidden')" class="h-10 w-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition-colors">
                        <i class="ph ph-x text-xl"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4">
                    @forelse($classrooms as $classroom)
                    <div class="border border-gray-200 rounded-2xl p-4 hover:border-primary/30 transition-colors">
                        <label class="flex items-center gap-3 cursor-pointer mb-3">
                            <input type="checkbox" name="classrooms[{{ $loop->index }}][classroom_id]" value="{{ $classroom->id }}" class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary/30 classroom-checkbox" onchange="
                                var card = this.closest('.border');
                                card.classList.toggle('bg-[#f8f9ff]', this.checked);
                                card.querySelectorAll('input[type=datetime-local], input[type=number]').forEach(function(el) {
                                    el.disabled = !this.checked;
                                }.bind(this));
                            ">
                            <span class="font-semibold text-gray-900">{{ $classroom->name }}</span>
                        </label>
                        <div class="grid grid-cols-3 gap-3 ml-8">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Mulai</label>
                                <input type="datetime-local" name="classrooms[{{ $loop->index }}][start_time]" value="{{ $exam->start_time->format('Y-m-d\TH:i') }}" disabled class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50 disabled:opacity-50">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Selesai</label>
                                <input type="datetime-local" name="classrooms[{{ $loop->index }}][end_time]" value="{{ $exam->end_time->format('Y-m-d\TH:i') }}" disabled class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50 disabled:opacity-50">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Durasi (mnt)</label>
                                <input type="number" name="classrooms[{{ $loop->index }}][duration_minutes]" value="{{ $exam->duration_minutes }}" min="1" disabled class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50 disabled:opacity-50">
                            </div>
                        </div>
                    </div>
                    @empty
                        <p class="text-center text-gray-500 py-8">Tidak ada kelas lain untuk diduplikat.</p>
                    @endforelse
                </div>

                <div class="p-6 pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="this.closest('#duplicate-modal').classList.add('hidden')" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-colors text-sm">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-secondary hover:bg-secondary-hover text-white rounded-xl font-medium transition-colors text-sm flex items-center gap-2">
                        <i class="ph ph-copy"></i> Duplikat
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i> {{ session('success') }}
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-8">

        <!-- Info Ujian -->
        <div class="md:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Informasi Jadwal</h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <span class="block text-gray-500 mb-0.5">Judul</span>
                        <span class="font-medium text-gray-900">{{ $exam->title }}</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 mb-0.5">Kelas</span>
                        <span class="font-medium text-gray-900">{{ $exam->classroom->name ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 mb-0.5">Waktu Ujian</span>
                        <span class="font-medium text-gray-900">{{ $exam->start_time->format('d/m/Y H:i') }} – {{ $exam->end_time->format('H:i') }}</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 mb-0.5">Durasi</span>
                        <span class="font-medium text-gray-900">{{ $exam->duration_minutes }} Menit</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 mb-0.5">Status</span>
                        @if($exam->is_active)
                            <span class="inline-flex items-center gap-1 text-green-700 font-semibold"><i class="ph-fill ph-check-circle"></i> Aktif</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-gray-500 font-semibold"><i class="ph ph-minus-circle"></i> Non-Aktif</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Modul Info -->
            @if($exam->module)
            <div class="bg-[#e8eaf5] border border-primary/10 p-6 rounded-[2rem]">
                <h3 class="text-base font-bold text-primary mb-2 flex items-center gap-2"><i class="ph ph-stack"></i> Modul Terhubung</h3>
                @if($exam->module->module_number)
                    <p class="text-xs font-semibold text-secondary uppercase tracking-wide mb-0.5">{{ $exam->module->module_number }}</p>
                @endif
                <p class="font-semibold text-gray-900 mb-3">{{ $exam->module->name }}</p>
                <p class="text-sm text-gray-600 mb-4">Total <strong>{{ $questions->count() }}</strong> soal dari modul ini.</p>
                <a href="{{ route('admin.courses.modules.show', [$exam->module->course_id, $exam->module]) }}" class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:text-primary-hover bg-white border border-primary/20 px-3 py-2 rounded-xl transition-colors">
                    <i class="ph ph-arrow-square-out"></i> Kelola Soal di Modul
                </a>
            </div>
            @else
            <div class="bg-gray-50 border border-gray-200 p-6 rounded-[2rem]">
                <h3 class="text-base font-bold text-gray-700 mb-2 flex items-center gap-2"><i class="ph ph-warning"></i> Belum Ada Modul</h3>
                <p class="text-sm text-gray-500 mb-3">Ujian ini belum terhubung ke modul. Edit jadwal dan pilih modul agar soal tersedia.</p>
                <a href="{{ route('admin.exams.edit', $exam) }}" class="inline-flex items-center gap-2 text-sm font-medium text-primary bg-white border border-gray-300 px-3 py-2 rounded-xl hover:bg-[#e8eaf5] transition-colors">
                    <i class="ph ph-pencil-simple"></i> Pilih Modul
                </a>
            </div>
            @endif
        </div>

        <!-- Preview Soal -->
        <div class="md:col-span-2">
            <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Preview Soal <span class="text-sm font-normal text-gray-500">({{ $questions->count() }} soal)</span></h3>
                </div>

                <div class="space-y-6">
                    @forelse($questions as $index => $question)
                        <div class="p-5 border border-gray-100 rounded-2xl bg-gray-50/50">
                            <div class="flex justify-between items-start mb-3">
                                <span class="inline-flex items-center justify-center bg-primary text-white h-8 w-8 rounded-full font-bold text-sm flex-shrink-0">
                                    {{ $index + 1 }}
                                </span>
                            </div>

                            @if($question->image)
                                <div class="mb-4">
                                    <img src="{{ asset('storage/' . $question->image) }}" alt="Gambar Soal" class="max-h-48 rounded-lg border border-gray-200 object-contain bg-white p-1">
                                </div>
                            @endif

                            <p class="text-gray-900 font-medium mb-4 whitespace-pre-wrap">{{ $question->content }}</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach($question->options as $option)
                                    <div class="px-4 py-2.5 rounded-xl border {{ $option->is_correct ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200' }} flex gap-3">
                                        @if($option->is_correct)
                                            <i class="ph-fill ph-check-circle text-green-500 text-lg flex-shrink-0"></i>
                                        @else
                                            <i class="ph ph-circle text-gray-300 text-lg flex-shrink-0"></i>
                                        @endif
                                        <span class="text-sm {{ $option->is_correct ? 'text-green-800 font-semibold' : 'text-gray-600' }}">{{ $option->content }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 border-2 border-dashed border-gray-200 rounded-2xl">
                            <div class="inline-flex h-16 w-16 bg-gray-100 rounded-full items-center justify-center text-gray-400 mb-4">
                                <i class="ph ph-list-dashes text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-1">Belum ada soal</h4>
                            <p class="text-gray-500 text-sm">Hubungkan ujian ke modul yang berisi soal, atau tambahkan soal ke modul terlebih dahulu.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
