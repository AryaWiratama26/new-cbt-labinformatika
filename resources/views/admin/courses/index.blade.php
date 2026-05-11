@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="flex justify-between items-end mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Mata Kuliah</h2>
            <p class="text-gray-500">Kelola daftar mata kuliah untuk CBT.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-8">
        
        <!-- Add New Course Form -->
        <div class="md:col-span-1">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Tambah Matkul Baru</h3>
                <form action="{{ route('admin.courses.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode Matkul</label>
                        <input type="text" name="code" value="{{ old('code') }}" required class="block w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50" placeholder="Contoh: IF101">
                        @error('code')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Mata Kuliah</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="block w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50" placeholder="Contoh: Basis Data">
                        @error('name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="w-full bg-[#3b4d3b] hover:bg-[#2d3b2d] text-white py-2.5 rounded-xl font-medium transition-colors">
                        Simpan Matkul
                    </button>
                </form>
            </div>
        </div>

        <!-- Course List -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Kode</th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Nama Mata Kuliah</th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($courses as $course)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-6 text-gray-900 font-medium">{{ $course->code }}</td>
                            <td class="py-4 px-6 text-gray-600">{{ $course->name }}</td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.courses.modules.index', $course) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-purple-600 hover:text-purple-800 px-3 py-1.5 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                                        <i class="ph ph-stack"></i> Modul
                                    </a>
                                    <form action="{{ route('admin.courses.destroy', $course) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus matkul ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Hapus">
                                            <i class="ph ph-trash text-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="py-8 text-center text-gray-500">
                                <div class="inline-flex h-12 w-12 bg-gray-50 rounded-full items-center justify-center text-gray-400 mb-3">
                                    <i class="ph ph-books text-2xl"></i>
                                </div>
                                <p>Belum ada mata kuliah.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
