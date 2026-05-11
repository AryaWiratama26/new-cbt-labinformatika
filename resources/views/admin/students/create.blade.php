@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.students.index') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Tambah Mahasiswa Manual</h2>
            <p class="text-gray-500">Isi data mahasiswa baru secara manual.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <form action="{{ route('admin.students.store') }}" method="POST" class="space-y-6">
            @csrf
            
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">NIM <span class="text-red-500">*</span></label>
                    <input type="text" name="nim" value="{{ old('nim') }}" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" placeholder="Contoh: 312010123">
                    @error('nim') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-500 mt-1">NIM juga akan digunakan sebagai Password awal.</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all" placeholder="Contoh: Budi Santoso">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Kelas <span class="text-red-500">*</span></label>
                @if($classrooms->isEmpty())
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-start gap-3">
                        <i class="ph ph-warning text-xl flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="font-semibold text-sm">Belum ada kelas tersedia.</p>
                            <p class="text-xs mt-1">Buat kelas terlebih dahulu sebelum menambah mahasiswa. <a href="{{ route('admin.classrooms') }}" class="underline font-bold hover:text-amber-900">Kelola Kelas →</a></p>
                        </div>
                    </div>
                @else
                    <select name="classroom_id" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all appearance-none bg-white">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" {{ old('classroom_id') == $classroom->id ? 'selected' : '' }}>{{ $classroom->name }}</option>
                        @endforeach
                    </select>
                @endif
                @error('classroom_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin.students.index') }}" class="px-6 py-3 text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl font-medium transition-colors">Batal</a>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-xl font-medium transition-colors">
                    Simpan Mahasiswa
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
