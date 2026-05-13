@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.exams.show', $exam) }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-3xl font-bold text-gray-900 mb-1">Monitoring Ujian</h2>
                <p class="text-gray-500">Pantau aktivitas peserta secara real-time.</p>
            </div>
        </div>
        <div class="flex items-center gap-3 text-sm text-gray-500">
            <i class="ph ph-clock-counter-clockwise text-lg"></i>
            <span id="last-update">Memperbarui...</span>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="participant-stat-card bg-white p-5 rounded-2xl shadow-sm border border-gray-100" data-stat="total">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Total Peserta</p>
            <p class="stat-value text-3xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="participant-stat-card bg-white p-5 rounded-2xl shadow-sm border border-primary/20" data-stat="in_progress">
            <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-1">Sedang Ujian</p>
            <p class="stat-value text-3xl font-bold text-gray-900">{{ $stats['in_progress'] }}</p>
        </div>
        <div class="participant-stat-card bg-white p-5 rounded-2xl shadow-sm border border-gray-200" data-stat="not_started">
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Belum Mulai</p>
            <p class="stat-value text-3xl font-bold text-gray-900">{{ $stats['not_started'] }}</p>
        </div>
        <div class="participant-stat-card bg-white p-5 rounded-2xl shadow-sm border border-secondary/20" data-stat="finished">
            <p class="text-xs font-semibold text-secondary uppercase tracking-wide mb-1">Selesai</p>
            <p class="stat-value text-3xl font-bold text-gray-900">{{ $stats['finished'] }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i> {{ session('success') }}
        </div>
    @endif

    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Daftar Peserta</h3>
                <p class="text-sm text-gray-500">{{ $exam->title }} — Kelas {{ $exam->classroom->name ?? '-' }}</p>
            </div>
            <div class="flex gap-2">
                <select id="filter-status" onchange="filterTable()" class="text-sm border border-gray-200 rounded-xl px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    <option value="all">Semua Status</option>
                    <option value="in_progress">Sedang Ujian</option>
                    <option value="finished">Selesai</option>
                    <option value="not_started">Belum Mulai</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="py-4 px-4 font-semibold text-gray-600 text-sm">No</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">NIM</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Nama Mahasiswa</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Status</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Mulai</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Selesai</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm text-right">Skor</th>
                    </tr>
                </thead>
                <tbody id="participants-tbody" class="divide-y divide-gray-100">
                    @forelse($participants as $index => $student)
                    <tr class="hover:bg-gray-50/50 transition-colors participant-row" data-status="{{ $student->status }}">
                        <td class="py-4 px-4 text-gray-500 text-sm">{{ $index + 1 }}</td>
                        <td class="py-4 px-6 font-mono text-sm text-gray-600">{{ $student->username }}</td>
                        <td class="py-4 px-6 font-medium text-gray-900">{{ $student->name }}</td>
                        <td class="py-4 px-6">
                            @if($student->status === 'in_progress')
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#e8eaf5] text-primary text-xs font-semibold rounded-full border border-primary/20">
                                    <span class="h-2 w-2 rounded-full bg-primary inline-block animate-pulse"></span> Sedang Ujian
                                </span>
                            @elseif($student->status === 'finished')
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#eeedf7] text-secondary text-xs font-semibold rounded-full border border-secondary/20">
                                    <i class="ph ph-check-circle text-sm"></i> Selesai
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full border border-gray-200">
                                    <i class="ph ph-clock text-sm"></i> Belum Mulai
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-sm text-gray-500">
                            {{ $student->started_at ? $student->started_at->format('H:i:s') : '-' }}
                        </td>
                        <td class="py-4 px-6 text-sm text-gray-500">
                            {{ $student->finished_at ? $student->finished_at->format('H:i:s') : '-' }}
                        </td>
                        <td class="py-4 px-6 text-right font-bold text-sm {{ $student->score !== null && $student->score >= $exam->passing_grade ? 'text-green-600' : ($student->score !== null ? 'text-red-500' : 'text-gray-400') }}">
                            {{ $student->score !== null ? $student->score : '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-gray-500">
                            <div class="inline-flex h-16 w-16 bg-gray-50 rounded-full items-center justify-center text-gray-400 mb-4">
                                <i class="ph ph-users text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-1">Tidak ada peserta</h4>
                            <p class="text-sm">Tidak ada mahasiswa di kelas ini.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let autoRefreshInterval;

function filterTable() {
    const status = document.getElementById('filter-status').value;
    document.querySelectorAll('.participant-row').forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

async function refreshData() {
    try {
        const response = await fetch('{{ route("admin.exams.monitor", $exam) }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        if (!response.ok) return;
        const data = await response.json();

        document.getElementById('last-update').textContent = 'Terakhir diperbarui: ' + data.time;

        document.querySelectorAll('.participant-row').forEach(row => {
            const nim = row.querySelector('td:nth-child(2)').textContent.trim();
            const p = data.participants[nim];
            if (!p) return;

            row.dataset.status = p.status;

            row.querySelector('td:nth-child(4)').innerHTML = p.status_badge;
            row.querySelector('td:nth-child(5)').textContent = p.started_at;
            row.querySelector('td:nth-child(6)').textContent = p.finished_at;

            const scoreTd = row.querySelector('td:nth-child(7)');
            scoreTd.textContent = p.score;
            scoreTd.className = 'py-4 px-6 text-right font-bold text-sm ' + p.score_class;
        });

        document.querySelectorAll('.participant-stat-card .stat-value').forEach(el => {
            const label = el.closest('.participant-stat-card').dataset.stat;
            if (data.stats[label] !== undefined) {
                el.textContent = data.stats[label];
            }
        });

        filterTable();
    } catch (e) {
        // silent
    }
}

document.addEventListener('DOMContentLoaded', function() {
    autoRefreshInterval = setInterval(refreshData, 10000);
});

document.querySelector('form')?.addEventListener('submit', function() {
    clearInterval(autoRefreshInterval);
});
</script>
@endsection
