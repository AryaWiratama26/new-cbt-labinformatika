@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.exams.index') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Buat Ujian Baru</h2>
            <p class="text-gray-500">Isi detail ujian praktikum di bawah ini.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700">
            <ul class="list-disc pl-5 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <form action="{{ route('admin.exams.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid md:grid-cols-2 gap-6">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Judul Ujian <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50" placeholder="Contoh: Kuis Pertemuan 1">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Modul Praktikum <span class="text-red-500">*</span></label>
                        <select name="module_id" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                            <option value="">-- Pilih Modul --</option>
                            @foreach($modules->groupBy('course.name') as $courseName => $courseModules)
                                <optgroup label="{{ $courseName }}">
                                    @foreach($courseModules as $module)
                                        <option value="{{ $module->id }}" {{ old('module_id') == $module->id ? 'selected' : '' }}>
                                            {{ $module->full_name }} ({{ $module->questions_count }} soal)
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Belum ada modul? <a href="{{ route('admin.courses.index') }}" class="text-primary hover:underline">Buat Modul di Mata Kuliah</a>.</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nilai Minimal (Passing Grade) <span class="text-red-500">*</span></label>
                        <input type="number" name="passing_grade" value="{{ old('passing_grade', 70) }}" min="0" max="100" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                        <p class="text-xs text-gray-500 mt-1">Mahasiswa dengan nilai di bawah ini berhak remedial.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Maksimal Percobaan <span class="text-red-500">*</span></label>
                        <input type="number" name="max_attempts" value="{{ old('max_attempts', 1) }}" min="1" max="10" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                        <p class="text-xs text-gray-500 mt-1">1 = tanpa remedial. 2+ = ada kesempatan remedial.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Deteksi Tab Berlebihan</label>
                        <input type="number" name="max_tab_switches" value="{{ old('max_tab_switches', '') }}" min="1" max="99" placeholder="Nonaktif" class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                        <p class="text-xs text-gray-500 mt-1">Kosongkan untuk nonaktif. Jika diisi, ujian auto-submit saat siswa melebihi batas.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">PIN Ujian (Opsional)</label>
                        <p class="text-xs text-gray-500 mt-1">PIN bisa diatur per kelas di bagian jadwal di bawah. Kosongkan jika tidak pakai PIN.</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Kelas Tujuan & Jadwal <span class="text-red-500">*</span></h3>
                <p class="text-sm text-gray-500 mb-4">Pilih satu atau lebih kelas. Atur jadwal dan PIN per kelas.</p>
                <div class="space-y-3" id="classrooms-container">
                    @foreach($classrooms as $classroom)
                    <div class="border border-gray-200 rounded-2xl p-4 hover:border-primary/30 transition-colors" data-classroom-id="{{ $classroom->id }}">
                        <label class="flex items-center gap-3 cursor-pointer mb-3">
                            <input type="checkbox" name="classrooms[{{ $loop->index }}][classroom_id]" value="{{ $classroom->id }}" class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary/30 classroom-checkbox"
                                {{ old('classrooms.' . $loop->index . '.classroom_id') == $classroom->id ? 'checked' : '' }}
                                onchange="toggleClassroom(this, {{ $loop->index }})">
                            <span class="font-semibold text-gray-900">{{ $classroom->name }}</span>
                        </label>
                        <div class="grid grid-cols-5 gap-3 ml-8">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Mulai</label>
                                <input type="datetime-local" name="classrooms[{{ $loop->index }}][start_time]"
                                    value="{{ old('classrooms.' . $loop->index . '.start_time') }}"
                                    disabled class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50 disabled:opacity-50 classroom-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Selesai</label>
                                <input type="datetime-local" name="classrooms[{{ $loop->index }}][end_time]"
                                    value="{{ old('classrooms.' . $loop->index . '.end_time') }}"
                                    disabled class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50 disabled:opacity-50 classroom-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Durasi (mnt)</label>
                                <input type="number" name="classrooms[{{ $loop->index }}][duration_minutes]"
                                    value="{{ old('classrooms.' . $loop->index . '.duration_minutes', 60) }}"
                                    min="1" disabled class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50 disabled:opacity-50 classroom-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">PIN</label>
                                <input type="text" name="classrooms[{{ $loop->index }}][pin]"
                                    value="{{ old('classrooms.' . $loop->index . '.pin') }}"
                                    maxlength="10" disabled placeholder="(opsional)"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50 disabled:opacity-50 classroom-input">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Aktif</label>
                                <label class="relative inline-flex items-center cursor-pointer mt-1">
                                    <input type="hidden" name="classrooms[{{ $loop->index }}][is_active]" value="0" disabled class="classroom-input">
                                    <input type="checkbox" name="classrooms[{{ $loop->index }}][is_active]" value="1"
                                        checked disabled
                                        class="sr-only peer classroom-input">
                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary disabled:opacity-50"></div>
                                </label>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi / Aturan (Opsional)</label>
                <textarea name="description" rows="3" class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50" placeholder="Tulis instruksi pengerjaan jika ada...">{{ old('description') }}</textarea>
            </div>

            <div class="flex flex-wrap gap-6 pt-4 border-t border-gray-100">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" checked>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    <span class="ml-3 text-sm font-medium text-gray-700">Aktifkan Ujian Langsung</span>
                </label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="require_fullscreen" value="0">
                    <input type="checkbox" name="require_fullscreen" value="1" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-secondary"></div>
                    <span class="ml-3 text-sm font-medium text-gray-700">Wajib Layar Penuh</span>
                </label>
            </div>

            <div class="pt-6">
                <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white py-3.5 rounded-xl font-medium transition-colors text-lg flex justify-center items-center gap-2">
                    <i class="ph ph-floppy-disk"></i> Simpan Ujian
                </button>
            </div>

        </form>
    </div>
</div>

@push('scripts')
<script>
function toggleClassroom(checkbox, index) {
    var card = checkbox.closest('[data-classroom-id]');
    card.classList.toggle('bg-[#f8f9ff]', checkbox.checked);
    card.querySelectorAll('.classroom-input').forEach(function(el) {
        el.disabled = !checkbox.checked;
    });
}
</script>
@endpush
@endsection
