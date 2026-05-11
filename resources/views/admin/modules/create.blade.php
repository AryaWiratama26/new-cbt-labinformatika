@extends('layouts.app')
@section('content')
<div class="max-w-3xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.courses.modules.index', $course) }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div>
            <p class="text-sm text-gray-500">{{ $course->name }}</p>
            <h2 class="text-3xl font-bold text-gray-900">Tambah Modul Baru</h2>
        </div>
    </div>

    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <form action="{{ route('admin.courses.modules.store', $course) }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Modul</label>
                    <input type="text" name="module_number" value="{{ old('module_number') }}" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" placeholder="Contoh: Modul 1">
                    @error('module_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Modul <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" placeholder="Contoh: Pengenalan Linux">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi (Opsional)</label>
                <textarea name="description" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all resize-y" placeholder="Deskripsi singkat isi modul ini...">{{ old('description') }}</textarea>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin.courses.modules.index', $course) }}" class="px-6 py-3 text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl font-medium">Batal</a>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-xl font-medium flex items-center gap-2">
                    <i class="ph ph-floppy-disk"></i> Simpan Modul
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
