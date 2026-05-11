@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Daftar Mahasiswa</h2>
            <p class="text-gray-500">Kelola data mahasiswa terdaftar.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.students.export') }}{{ request('classroom_id') ? '?classroom_id=' . request('classroom_id') : '' }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-download-simple"></i> Export CSV
            </a>
            <a href="{{ route('admin.students.move.form') }}" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-swap"></i> Pindahkan
            </a>
            <a href="{{ route('admin.students.create') }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-plus"></i> Tambah Manual
            </a>
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

    <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 md:p-6 border-b border-gray-100 bg-gray-50/50">
            <form method="GET" class="flex flex-col md:flex-row gap-3">
                <div class="flex-grow relative">
                    <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari NIM atau Nama..." class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                </div>
                <select name="classroom_id" class="px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">Semua Kelas</option>
                    @foreach($classrooms as $classroom)
                        <option value="{{ $classroom->id }}" {{ request('classroom_id') == $classroom->id ? 'selected' : '' }}>{{ $classroom->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-6 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl font-medium transition-colors">Filter</button>
                @if(request('search') || request('classroom_id'))
                    <a href="{{ route('admin.students.index') }}" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-medium transition-colors text-center">Reset</a>
                @endif
            </form>
        </div>

        <form id="bulk-form" action="{{ route('admin.students.bulk_delete') }}" method="POST">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[700px]">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="py-4 px-4 w-12">
                                <input type="checkbox" id="select-all" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                            </th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm">NIM</th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Nama Lengkap</th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Kelas</th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Terdaftar Sejak</th>
                            <th class="py-4 px-6 font-semibold text-gray-600 text-sm text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($students as $student)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-4">
                                <input type="checkbox" name="ids[]" value="{{ $student->id }}" class="student-checkbox w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                            </td>
                            <td class="py-4 px-6 font-mono text-gray-600">{{ $student->username }}</td>
                            <td class="py-4 px-6 font-medium text-gray-900">{{ $student->name }}</td>
                            <td class="py-4 px-6 text-sm text-gray-600">
                                <span class="px-3 py-1 bg-gray-100 border border-gray-200 rounded-full font-medium">{{ $student->classroom->name ?? 'Belum ada kelas' }}</span>
                            </td>
                            <td class="py-4 px-6 text-sm text-gray-500">{{ $student->created_at->format('d M Y') }}</td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.students.edit', $student) }}" class="text-blue-500 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50 transition-colors" title="Edit">
                                        <i class="ph ph-pencil-simple text-lg"></i>
                                    </a>
                                    <form action="{{ route('admin.students.reset_password', $student) }}" method="POST" class="inline-block">
                                        @csrf
                                        <button type="submit" class="text-amber-500 hover:text-amber-700 p-2 rounded-lg hover:bg-amber-50 transition-colors" title="Reset Password" onclick="return confirm('Reset password {{ $student->name }} ke NIM?')">
                                            <i class="ph ph-key text-lg"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.students.destroy', $student) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus {{ $student->name }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Hapus">
                                            <i class="ph ph-trash text-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-gray-500">Belum ada data mahasiswa.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="p-4 md:p-6 border-t border-gray-100 flex flex-col md:flex-row justify-between items-center gap-3">
            <button type="button" id="bulk-delete-btn" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl font-medium transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                <i class="ph ph-trash"></i> Hapus Terpilih (<span id="selected-count">0</span>)
            </button>
            <div>{{ $students->links() }}</div>
        </div>
    </div>
</div>

<script>
document.getElementById('select-all')?.addEventListener('change', function() {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
    updateBulkButton();
});
document.querySelectorAll('.student-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkButton);
});
function updateBulkButton() {
    const checked = document.querySelectorAll('.student-checkbox:checked').length;
    const btn = document.getElementById('bulk-delete-btn');
    document.getElementById('selected-count').textContent = checked;
    btn.disabled = checked === 0;
}
document.getElementById('bulk-delete-btn')?.addEventListener('click', function() {
    const checked = document.querySelectorAll('.student-checkbox:checked').length;
    if (checked === 0) return;
    if (confirm(`Hapus ${checked} mahasiswa yang dipilih?`)) {
        document.getElementById('bulk-form').submit();
    }
});
</script>
@endsection
