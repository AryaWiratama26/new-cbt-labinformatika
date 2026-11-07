@extends('layouts.app')
@section('content')
@php
    $selectedType = old('type', 'multiple_choice');
    $languages = \App\Models\Question::SUPPORTED_LANGUAGES;
@endphp
<div class="max-w-4xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.courses.modules.show', [$course, $module]) }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div class="flex-1">
            <p class="text-sm text-gray-500">{{ $module->full_name }}</p>
            <h2 class="text-3xl font-bold text-gray-900">Tambah Soal Baru</h2>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <form action="{{ route('admin.courses.modules.questions.store', [$course, $module]) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf

            <div>
                <label class="block text-sm font-bold text-gray-900 mb-2">Tipe Soal <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="multiple_choice" class="peer sr-only type-toggle" {{ $selectedType === 'multiple_choice' ? 'checked' : '' }}>
                        <div class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 peer-checked:border-primary peer-checked:bg-primary/5 peer-checked:ring-1 peer-checked:ring-primary transition-colors">
                            <i class="ph ph-list-checks text-2xl text-primary"></i>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Pilihan Ganda</p>
                                <p class="text-xs text-gray-500">Empat opsi dengan satu jawaban benar</p>
                            </div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="coding" class="peer sr-only type-toggle" {{ $selectedType === 'coding' ? 'checked' : '' }}>
                        <div class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 peer-checked:border-primary peer-checked:bg-primary/5 peer-checked:ring-1 peer-checked:ring-primary transition-colors">
                            <i class="ph ph-code text-2xl text-secondary"></i>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Koding</p>
                                <p class="text-xs text-gray-500">Mahasiswa menulis kode, dinilai AI/manual</p>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-900 mb-2">Teks Pertanyaan <span class="text-red-500">*</span></label>
                <textarea name="content" rows="4" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all resize-y" placeholder="Tuliskan pertanyaan di sini...">{{ old('content') }}</textarea>
                @error('content') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-gray-900 mb-2">Gambar Pendukung (Opsional)</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'preview-create')" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100 border border-gray-200 rounded-xl cursor-pointer">
                    <div id="preview-create" class="mt-2 hidden">
                        <img class="max-h-40 rounded-lg border border-gray-200 object-contain bg-white p-1">
                    </div>
                    @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-900 mb-2">Kategori</label>
                    <select name="category" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none bg-white">
                        <option value="">-- Pilih Kategori --</option>
                        <option value="mudah" {{ old('category') == 'mudah' ? 'selected' : '' }}>Mudah</option>
                        <option value="sedang" {{ old('category') == 'sedang' ? 'selected' : '' }}>Sedang</option>
                        <option value="sulit" {{ old('category') == 'sulit' ? 'selected' : '' }}>Sulit</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-900 mb-2">Pembahasan (Opsional)</label>
                <textarea name="explanation" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all resize-y" placeholder="Tulis pembahasan jawaban...">{{ old('explanation') }}</textarea>
            </div>

            <hr class="border-gray-100">

            <div id="mcq-section" class="{{ $selectedType === 'coding' ? 'hidden' : '' }}">
                <label class="block text-sm font-bold text-gray-900 mb-4">Pilihan Ganda & Kunci Jawaban <span class="text-red-500">*</span></label>
                <div class="space-y-4">
                    @foreach(['A','B','C','D'] as $index => $label)
                        <div class="flex items-start gap-4">
                            <div class="pt-3">
                                <label class="cursor-pointer flex items-center justify-center w-8 h-8 rounded-full border-2 border-gray-200 transition-colors relative" for="correct_{{ $index }}">
                                    <input type="radio" name="correct_option" id="correct_{{ $index }}" value="{{ $index }}" {{ old('correct_option') == $index ? 'checked' : '' }} class="peer sr-only mcq-required">
                                    <span class="peer-checked:hidden text-sm font-bold text-gray-400">{{ $label }}</span>
                                    <i class="ph-fill ph-check-circle text-3xl text-green-500 absolute hidden peer-checked:block bg-white rounded-full"></i>
                                </label>
                            </div>
                            <div class="flex-grow">
                                <input type="text" name="options[]" value="{{ old('options.'.$index) }}" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all mcq-required" placeholder="Opsi {{ $label }}">
                            </div>
                        </div>
                    @endforeach
                    @error('correct_option') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div id="coding-section" class="{{ $selectedType === 'coding' ? '' : 'hidden' }} space-y-6">
                <div>
                    <label class="block text-sm font-bold text-gray-900 mb-2">Bahasa Pemrograman <span class="text-red-500">*</span></label>
                    <select name="code_language" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none bg-white coding-required">
                        <option value="">-- Pilih Bahasa --</option>
                        @foreach($languages as $key => $langLabel)
                            <option value="{{ $key }}" {{ old('code_language') == $key ? 'selected' : '' }}>{{ $langLabel }}</option>
                        @endforeach
                    </select>
                    @error('code_language') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-900 mb-2">Kode Awal (Starter Code)</label>
                    <textarea name="starter_code" rows="6" spellcheck="false" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all resize-y font-mono text-sm bg-gray-50" placeholder="Kode yang sudah terisi di editor mahasiswa...">{{ old('starter_code') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-900 mb-2">Kunci / Solusi (Opsional)</label>
                    <textarea name="solution_code" rows="6" spellcheck="false" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all resize-y font-mono text-sm bg-gray-50" placeholder="Solusi referensi, hanya terlihat oleh admin...">{{ old('solution_code') }}</textarea>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin.courses.modules.show', [$course, $module]) }}" class="px-6 py-3 text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl font-medium">Batal</a>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-xl font-medium flex items-center gap-2">
                    <i class="ph ph-floppy-disk text-lg"></i> Simpan Soal
                </button>
            </div>
        </form>
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

function toggleQuestionType(type) {
    const isCoding = type === 'coding';
    document.getElementById('mcq-section').classList.toggle('hidden', isCoding);
    document.getElementById('coding-section').classList.toggle('hidden', !isCoding);

    document.querySelectorAll('.mcq-required').forEach((el) => {
        if (isCoding) el.removeAttribute('required');
        else el.setAttribute('required', 'required');
    });
    document.querySelectorAll('.coding-required').forEach((el) => {
        if (isCoding) el.setAttribute('required', 'required');
        else el.removeAttribute('required');
    });

    if (isCoding) {
        const checked = document.querySelector('.mcq-required[name="correct_option"]:checked');
        if (checked) checked.checked = false;
    }
}

document.querySelectorAll('.type-toggle').forEach((radio) => {
    radio.addEventListener('change', function () {
        if (this.checked) toggleQuestionType(this.value);
    });
});

toggleQuestionType(document.querySelector('.type-toggle:checked')?.value || 'multiple_choice');
</script>
@endsection
