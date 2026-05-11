@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Daftar Kelas</h2>
            <p class="text-gray-500">Manajemen kelas dan tahun ajaran.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.students.move.form') }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-swap"></i> Pindahkan Mhs
            </a>
            <button type="button" onclick="document.getElementById('create-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-plus"></i> Tambah Kelas
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl"></i> {{ session('error') }}
        </div>
    @endif

    <div class="bg-white p-4 md:p-6 rounded-[2rem] shadow-sm border border-gray-100 mb-6">
        <form method="GET" class="flex gap-3">
            <div class="flex-grow relative">
                <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama kelas..." class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl font-medium transition-colors">Cari</button>
            @if(request('search'))
                <a href="{{ route('admin.classrooms') }}" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-colors">Reset</a>
            @endif
        </form>
    </div>

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($classrooms as $classroom)
            <div class="bg-white p-6 rounded-2xl border border-gray-100 hover:border-orange-200 hover:shadow-md transition-all group">
                <div class="flex justify-between items-start mb-4">
                    <div class="h-12 w-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center group-hover:bg-orange-600 group-hover:text-white transition-all">
                        <i class="ph ph-chalkboard-teacher text-2xl"></i>
                    </div>
                    <div class="flex gap-1">
                        <button onclick="editClassroom({{ $classroom->id }}, '{{ $classroom->name }}', '{{ $classroom->academic_year ?? '' }}', '{{ $classroom->semester ?? '' }}')" class="text-blue-500 hover:text-blue-700 p-1.5 rounded-lg hover:bg-blue-50 transition-colors">
                            <i class="ph ph-pencil-simple text-lg"></i>
                        </button>
                        @if($classroom->users_count === 0)
                            <form action="{{ route('admin.classrooms.destroy', $classroom) }}" method="POST" onsubmit="return confirm('Hapus kelas {{ $classroom->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 p-1.5 rounded-lg hover:bg-red-50 transition-colors">
                                    <i class="ph ph-trash text-lg"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-1">{{ $classroom->name }}</h3>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    @if($classroom->academic_year)
                        <span class="px-2 py-0.5 bg-gray-100 rounded-full text-xs">{{ $classroom->academic_year }}</span>
                    @endif
                    @if($classroom->semester)
                        <span class="px-2 py-0.5 bg-gray-100 rounded-full text-xs">{{ $classroom->semester }}</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500"><span class="font-bold text-gray-900">{{ $classroom->users_count }}</span> Mahasiswa</p>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-gray-500 bg-white rounded-2xl border border-gray-100">
                <p>Belum ada data kelas.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8">{{ $classrooms->links() }}</div>
</div>

<div id="create-modal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] p-8 max-w-lg w-full shadow-2xl">
        <h3 class="text-2xl font-bold text-gray-900 mb-6">Tambah Kelas Baru</h3>
        <form action="{{ route('admin.classrooms.store') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Kelas <span class="text-red-500">*</span></label>
                <input type="text" name="name" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: TI.22.A.1">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tahun Ajaran</label>
                <input type="text" name="academic_year" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: 2025/2026">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Semester</label>
                <select name="semester" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">-- Pilih --</option>
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="this.closest('#create-modal').classList.add('hidden')" class="px-6 py-3 text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl font-medium">Batal</button>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-xl font-medium">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="edit-modal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] p-8 max-w-lg w-full shadow-2xl">
        <h3 class="text-2xl font-bold text-gray-900 mb-6">Edit Kelas</h3>
        <form method="POST" class="space-y-5" id="edit-form">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Kelas <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="edit-name" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tahun Ajaran</label>
                <input type="text" name="academic_year" id="edit-academic-year" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Semester</label>
                <select name="semester" id="edit-semester" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">-- Pilih --</option>
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                </select>
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="this.closest('#edit-modal').classList.add('hidden')" class="px-6 py-3 text-gray-600 bg-gray-50 hover:bg-gray-100 rounded-xl font-medium">Batal</button>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-xl font-medium">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function editClassroom(id, name, academicYear, semester) {
    document.getElementById('edit-form').action = `/admin/classrooms/${id}`;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-academic-year').value = academicYear;
    document.getElementById('edit-semester').value = semester;
    document.getElementById('edit-modal').classList.remove('hidden');
}
</script>
@endsection
