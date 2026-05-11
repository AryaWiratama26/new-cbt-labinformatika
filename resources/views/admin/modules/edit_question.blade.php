@extends('layouts.app')
@section('content')
<div class="max-w-4xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.courses.modules.show', [$course, $module]) }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div>
            <p class="text-sm text-gray-500">{{ $module->full_name }}</p>
            <h2 class="text-3xl font-bold text-gray-900">Edit Soal</h2>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <form action="{{ route('admin.courses.modules.questions.update', [$course, $module, $question]) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-bold text-gray-900 mb-2">Teks Pertanyaan <span class="text-red-500">*</span></label>
                <textarea name="content" rows="4" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all resize-y">{{ old('content', $question->content) }}</textarea>
                @error('content') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-gray-900 mb-2">Gambar (biarkan kosong jika tidak diubah)</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'preview-edit')" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100 border border-gray-200 rounded-xl cursor-pointer">
                    <div id="preview-edit" class="mt-2 hidden">
                        <img class="max-h-40 rounded-lg border border-gray-200 object-contain bg-white p-1">
                    </div>
                    @if($question->image)
                        <div class="mt-2 flex items-center gap-3">
                            <img src="{{ asset('storage/' . $question->image) }}" class="h-12 w-12 rounded-lg object-cover border">
                            <span class="text-xs text-gray-500">Gambar saat ini</span>
                            <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer ml-auto">
                                <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 text-red-500 focus:ring-red-500">
                                Hapus gambar
                            </label>
                        </div>
                    @endif
                    @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-900 mb-2">Kategori</label>
                    <select name="category" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none bg-white">
                        <option value="">-- Pilih Kategori --</option>
                        <option value="mudah" {{ old('category', $question->category) == 'mudah' ? 'selected' : '' }}>Mudah</option>
                        <option value="sedang" {{ old('category', $question->category) == 'sedang' ? 'selected' : '' }}>Sedang</option>
                        <option value="sulit" {{ old('category', $question->category) == 'sulit' ? 'selected' : '' }}>Sulit</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-900 mb-2">Pembahasan (Opsional)</label>
                <textarea name="explanation" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all resize-y">{{ old('explanation', $question->explanation) }}</textarea>
            </div>

            <hr class="border-gray-100">

            <div>
                <label class="block text-sm font-bold text-gray-900 mb-4">Pilihan Ganda & Kunci Jawaban <span class="text-red-500">*</span></label>
                @php
                    $correctIdx = $question->options->where('is_correct', true)->first() ? $question->options->values()->search(fn($o) => $o->is_correct) : 0;
                @endphp
                <div class="space-y-4">
                    @foreach(['A','B','C','D'] as $index => $label)
                        <div class="flex items-start gap-4">
                            <div class="pt-3">
                                <label class="cursor-pointer flex items-center justify-center w-8 h-8 rounded-full border-2 border-gray-200 transition-colors relative" for="correct_{{ $index }}">
                                    <input type="radio" name="correct_option" id="correct_{{ $index }}" value="{{ $index }}" {{ old('correct_option', $correctIdx) == $index ? 'checked' : '' }} required class="peer sr-only">
                                    <span class="peer-checked:hidden text-sm font-bold text-gray-400">{{ $label }}</span>
                                    <i class="ph-fill ph-check-circle text-3xl text-green-500 absolute hidden peer-checked:block bg-white rounded-full"></i>
                                </label>
                            </div>
                            <div class="flex-grow">
                                <input type="text" name="options[]" value="{{ old('options.'.$index, $question->options[$index]->content ?? '') }}" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" placeholder="Opsi {{ $label }}">
                            </div>
                        </div>
                    @endforeach
                    @error('correct_option') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin.courses.modules.show', [$course, $module]) }}" class="px-6 py-3 text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl font-medium">Batal</a>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-xl font-medium flex items-center gap-2">
                    <i class="ph ph-floppy-disk text-lg"></i> Simpan Perubahan
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
</script>
@endsection
