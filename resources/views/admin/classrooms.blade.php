@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Daftar Kelas</h2>
            <p class="text-gray-500">Manajemen kelas mahasiswa.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-arrow-left text-lg"></i> Kembali
            </a>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($classrooms as $classroom)
                <div class="border border-gray-100 p-6 rounded-2xl hover:border-orange-200 hover:bg-orange-50/30 transition-colors">
                    <div class="flex justify-between items-start mb-4">
                        <div class="h-12 w-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center">
                            <i class="ph ph-chalkboard-teacher text-2xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-1">{{ $classroom->name }}</h3>
                    <p class="text-sm text-gray-500"><span class="font-medium text-gray-900">{{ $classroom->users_count }}</span> Mahasiswa</p>
                </div>
            @empty
                <div class="col-span-full py-12 text-center text-gray-500">
                    <p>Belum ada data kelas terdaftar.</p>
                </div>
            @endforelse
        </div>
        
        <div class="mt-8">
            {{ $classrooms->links() }}
        </div>
    </div>
</div>
@endsection
