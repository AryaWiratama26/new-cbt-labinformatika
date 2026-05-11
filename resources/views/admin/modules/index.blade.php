@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div>
            <p class="text-sm text-gray-500 mb-1">{{ $course->code }} — {{ $course->name }}</p>
            <h2 class="text-3xl font-bold text-gray-900">Modul Praktikum</h2>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.courses.index') }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-arrow-left text-lg"></i> Kembali
            </a>
            <a href="{{ route('admin.courses.modules.create', $course) }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-plus text-lg"></i> Tambah Modul
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i> {{ session('success') }}
        </div>
    @endif

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($modules as $module)
            <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden flex flex-col">
                <div class="p-6 flex-grow">
                    <div class="flex justify-between items-start mb-4">
                        <div class="h-12 w-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center">
                            <i class="ph ph-stack text-2xl"></i>
                        </div>
                        <span class="text-xs font-medium px-3 py-1 bg-gray-100 text-gray-600 rounded-full">{{ $module->questions_count }} Soal</span>
                    </div>
                    @if($module->module_number)
                        <p class="text-xs font-semibold text-purple-600 uppercase tracking-wide mb-1">{{ $module->module_number }}</p>
                    @endif
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $module->name }}</h3>
                    @if($module->description)
                        <p class="text-sm text-gray-500 line-clamp-2">{{ $module->description }}</p>
                    @endif
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <a href="{{ route('admin.courses.modules.show', [$course, $module]) }}" class="flex-1 text-center py-2.5 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-xl font-medium transition-colors text-sm">
                        Kelola Soal
                    </a>
                    <form action="{{ route('admin.courses.modules.destroy', [$course, $module]) }}" method="POST" onsubmit="return confirm('Hapus modul dan semua soalnya?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-2.5 text-red-500 hover:bg-red-50 rounded-xl transition-colors">
                            <i class="ph ph-trash text-lg"></i>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="md:col-span-3 py-16 text-center bg-white rounded-[2rem] border border-gray-100">
                <div class="inline-flex h-16 w-16 bg-gray-100 rounded-full items-center justify-center text-gray-400 mb-4">
                    <i class="ph ph-stack text-3xl"></i>
                </div>
                <h4 class="text-lg font-bold text-gray-900 mb-1">Belum ada modul</h4>
                <p class="text-gray-500 text-sm mb-4">Tambahkan modul praktikum untuk mata kuliah ini.</p>
                <a href="{{ route('admin.courses.modules.create', $course) }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-5 rounded-xl font-medium text-sm">
                    <i class="ph ph-plus"></i> Tambah Modul Pertama
                </a>
            </div>
        @endforelse
    </div>
</div>
@endsection
