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
        <div class="flex gap-3">
            <a href="{{ route('admin.exams.results', $exam) }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-chart-bar text-lg"></i> Lihat Hasil
            </a>
            <a href="{{ route('admin.exams.edit', $exam) }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-pencil-simple text-lg"></i> Edit Jadwal
            </a>
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
            <div class="bg-purple-50 border border-purple-200 p-6 rounded-[2rem]">
                <h3 class="text-base font-bold text-purple-900 mb-2 flex items-center gap-2"><i class="ph ph-stack"></i> Modul Terhubung</h3>
                @if($exam->module->module_number)
                    <p class="text-xs font-semibold text-purple-500 uppercase tracking-wide mb-0.5">{{ $exam->module->module_number }}</p>
                @endif
                <p class="font-semibold text-purple-800 mb-3">{{ $exam->module->name }}</p>
                <p class="text-sm text-purple-700 mb-4">Total <strong>{{ $questions->count() }}</strong> soal dari modul ini.</p>
                <a href="{{ route('admin.courses.modules.show', [$exam->module->course_id, $exam->module]) }}" class="inline-flex items-center gap-2 text-sm font-medium text-purple-700 hover:text-purple-900 bg-white border border-purple-200 px-3 py-2 rounded-xl transition-colors">
                    <i class="ph ph-arrow-square-out"></i> Kelola Soal di Modul
                </a>
            </div>
            @else
            <div class="bg-amber-50 border border-amber-200 p-6 rounded-[2rem]">
                <h3 class="text-base font-bold text-amber-800 mb-2 flex items-center gap-2"><i class="ph ph-warning"></i> Belum Ada Modul</h3>
                <p class="text-sm text-amber-700 mb-3">Ujian ini belum terhubung ke modul. Edit jadwal dan pilih modul agar soal tersedia.</p>
                <a href="{{ route('admin.exams.edit', $exam) }}" class="inline-flex items-center gap-2 text-sm font-medium text-amber-800 bg-white border border-amber-300 px-3 py-2 rounded-xl hover:bg-amber-50 transition-colors">
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
