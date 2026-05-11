@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.students.index') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Pindahkan Mahasiswa</h2>
            <p class="text-gray-500">Pindahkan mahasiswa antar kelas.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i> {{ session('success') }}
        </div>
    @endif

    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <form action="{{ route('admin.students.move') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kelas Asal <span class="text-red-500">*</span></label>
                    <select name="from_classroom_id" id="from-classroom" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                        <option value="">-- Pilih Kelas Asal --</option>
                        @foreach($classrooms as $classroom)
                            <option value="{{ $classroom->id }}">{{ $classroom->name }} ({{ $classroom->users_count ?? 0 }} mhs)</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kelas Tujuan <span class="text-red-500">*</span></label>
                    <select name="to_classroom_id" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                        <option value="">-- Pilih Kelas Tujuan --</option>
                        @foreach($classrooms as $classroom)
                            <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                        @endforeach
                    </select>
                    @error('to_classroom_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Mahasiswa yang Dipindahkan</label>
                <div id="student-list" class="border-2 border-dashed border-gray-200 rounded-2xl p-6 max-h-80 overflow-y-auto">
                    <p class="text-gray-400 text-center py-8">Pilih kelas asal terlebih dahulu</p>
                </div>
                @error('student_ids') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white py-3.5 rounded-xl font-medium transition-colors flex justify-center items-center gap-2">
                    <i class="ph ph-swap text-xl"></i> Pindahkan Mahasiswa
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('from-classroom')?.addEventListener('change', function() {
    const classroomId = this.value;
    const list = document.getElementById('student-list');
    if (!classroomId) {
        list.innerHTML = '<p class="text-gray-400 text-center py-8">Pilih kelas asal terlebih dahulu</p>';
        return;
    }
    list.innerHTML = '<p class="text-gray-400 text-center py-8">Memuat data...</p>';
    fetch(`/admin/students?classroom_id=${classroomId}&ajax=1`)
        .then(r => r.json())
        .then(students => {
            if (!students.length) {
                list.innerHTML = '<p class="text-gray-400 text-center py-8">Tidak ada mahasiswa di kelas ini</p>';
                return;
            }
            let html = '<div class="space-y-2">';
            students.forEach(s => {
                html += `<label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="student_ids[]" value="${s.id}" class="w-4 h-4 text-primary rounded border-gray-300">
                    <span class="font-mono text-sm text-gray-600">${s.username}</span>
                    <span class="font-medium text-gray-900">${s.name}</span>
                </label>`;
            });
            html += '</div>';
            list.innerHTML = html;
        })
        .catch(() => {
            list.innerHTML = '<p class="text-red-500 text-center py-8">Gagal memuat data</p>';
        });
});
</script>
@endsection
