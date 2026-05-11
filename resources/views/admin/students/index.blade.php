@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Daftar Mahasiswa</h2>
            <p class="text-gray-500">Daftar semua mahasiswa yang terdaftar di sistem.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-arrow-left text-lg"></i> Kembali
            </a>
            <a href="{{ route('admin.students.create') }}" class="inline-flex items-center gap-2 bg-[#3b4d3b] hover:bg-[#2d3b2d] text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-plus text-lg"></i> Tambah Manual
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[600px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm rounded-l-xl">NIM</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Nama Lengkap</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Kelas</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Terdaftar Sejak</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm text-right rounded-r-xl">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($students as $student)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-6 font-mono text-gray-600">{{ $student->username }}</td>
                        <td class="py-4 px-6 font-medium text-gray-900">{{ $student->name }}</td>
                        <td class="py-4 px-6 text-sm text-gray-600">
                            <span class="px-3 py-1 bg-gray-100 border border-gray-200 rounded-full font-medium">{{ $student->classroom->name ?? 'Belum ada kelas' }}</span>
                        </td>
                        <td class="py-4 px-6 text-sm text-gray-500">{{ $student->created_at->format('d M Y') }}</td>
                        <td class="py-4 px-6 text-right">
                            <form action="{{ route('admin.students.destroy', $student) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus mahasiswa ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Hapus">
                                    <i class="ph ph-trash text-lg"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-gray-500">Belum ada data mahasiswa.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-6">
            {{ $students->links() }}
        </div>
    </div>
</div>
@endsection
