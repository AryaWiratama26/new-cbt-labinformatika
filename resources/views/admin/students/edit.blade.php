@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.students.index') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Edit Mahasiswa</h2>
            <p class="text-gray-500">Perbarui data mahasiswa.</p>
        </div>
    </div>

    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <form action="{{ route('admin.students.update', $user) }}" method="POST" class="space-y-6">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">NIM <span class="text-red-500">*</span></label>
                <input type="text" name="nim" value="{{ old('nim', $user->username) }}" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                @error('nim') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Kelas <span class="text-red-500">*</span></label>
                <select name="classroom_id" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                    @foreach($classrooms as $classroom)
                        <option value="{{ $classroom->id }}" {{ old('classroom_id', $user->classroom_id) == $classroom->id ? 'selected' : '' }}>{{ $classroom->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pt-4 flex justify-end gap-3">
                <a href="{{ route('admin.students.index') }}" class="px-6 py-3 text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl font-medium">Batal</a>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-xl font-medium flex items-center gap-2">
                    <i class="ph ph-floppy-disk text-lg"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
