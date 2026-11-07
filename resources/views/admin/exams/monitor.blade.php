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
                <p class="text-sm text-gray-500">{{ $exam->title }} — Kelas {{ $exam->classrooms->pluck('name')->implode(', ') ?: '-' }}</p>
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
            <table class="w-full text-left border-collapse min-w-[980px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="py-4 px-4 font-semibold text-gray-600 text-sm">No</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">NIM</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Nama Mahasiswa</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Status</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Kamera</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Event</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Mulai</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Selesai</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm text-right">Skor</th>
                    </tr>
                </thead>
                <tbody id="participants-tbody" class="divide-y divide-gray-100">
                    @forelse($participants as $index => $student)
                    <tr class="hover:bg-gray-50/50 transition-colors participant-row" data-status="{{ $student->status }}" data-session-id="{{ $student->session_id ?? '' }}">
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
                        <td class="py-4 px-6">
                            <div class="flex flex-col gap-1.5 text-xs">
                                <span class="laptop-camera-status inline-flex items-center gap-1.5 {{ $student->laptop_seen_at && $student->laptop_seen_at->greaterThan(now()->subSeconds(30)) ? 'text-green-700' : 'text-gray-500' }}">
                                    <span class="h-2 w-2 rounded-full {{ $student->laptop_seen_at && $student->laptop_seen_at->greaterThan(now()->subSeconds(30)) ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                                    Laptop <span class="laptop-camera-time">{{ $student->laptop_seen_at ? $student->laptop_seen_at->format('H:i:s') : '-' }}</span>
                                </span>
                                <span class="phone-camera-status inline-flex items-center gap-1.5 {{ $student->phone_seen_at && $student->phone_seen_at->greaterThan(now()->subSeconds(30)) ? 'text-green-700' : 'text-gray-500' }}">
                                    <span class="h-2 w-2 rounded-full {{ $student->phone_seen_at && $student->phone_seen_at->greaterThan(now()->subSeconds(30)) ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                                    HP <span class="phone-camera-time">{{ $student->phone_seen_at ? $student->phone_seen_at->format('H:i:s') : '-' }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            @if($student->session_id)
                                <button type="button" class="event-button inline-flex items-center gap-1.5 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-xs font-semibold text-primary hover:bg-primary/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" data-session-id="{{ $student->session_id }}" data-student-name="{{ $student->name }}" onclick="openProctorEvents(this)">
                                    <i class="ph ph-list-magnifying-glass text-base"></i>
                                    <span class="event-count">{{ $student->proctor_event_count }}</span>
                                </button>
                            @else
                                <span class="text-xs text-gray-400">-</span>
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
                        <td colspan="9" class="py-12 text-center text-gray-500">
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

<div id="proctor-events-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-950/40 p-4" role="dialog" aria-modal="true" aria-labelledby="proctor-events-title">
    <div class="w-full max-w-xl max-h-[85vh] overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Camera proctoring</p>
                <h3 id="proctor-events-title" class="mt-1 text-xl font-bold text-gray-900">Event peserta</h3>
            </div>
            <button type="button" onclick="closeProctorEvents()" aria-label="Tutup detail event" class="h-10 w-10 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>
        <div id="proctor-events-list" class="max-h-[60vh] overflow-y-auto p-6">
            <p class="text-sm text-gray-500">Memuat event...</p>
        </div>
    </div>
</div>

<script>
let autoRefreshInterval;
const proctorEventsUrlTemplate = @json(route('admin.proctor.events', ['session' => '__SESSION__']));

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
            updateCameraStatus(row, p);
            const eventCount = row.querySelector('.event-count');
            if (eventCount) eventCount.textContent = p.proctor_event_count;
            row.querySelector('td:nth-child(7)').textContent = p.started_at;
            row.querySelector('td:nth-child(8)').textContent = p.finished_at;

            const scoreTd = row.querySelector('td:nth-child(9)');
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

function updateCameraStatus(row, participant) {
    const cameras = [
        { selector: '.laptop-camera-status', online: participant.laptop_online, time: participant.laptop_seen_at },
        { selector: '.phone-camera-status', online: participant.phone_online, time: participant.phone_seen_at },
    ];

    cameras.forEach(camera => {
        const status = row.querySelector(camera.selector);
        if (!status) return;
        const dot = status.querySelector('span');
        status.classList.toggle('text-green-700', camera.online);
        status.classList.toggle('text-gray-500', !camera.online);
        dot.classList.toggle('bg-green-500', camera.online);
        dot.classList.toggle('bg-gray-300', !camera.online);
        status.querySelector(camera.selector === '.laptop-camera-status' ? '.laptop-camera-time' : '.phone-camera-time').textContent = camera.time || '-';
    });
}

async function openProctorEvents(button) {
    const modal = document.getElementById('proctor-events-modal');
    const list = document.getElementById('proctor-events-list');
    const sessionId = button.dataset.sessionId;
    document.getElementById('proctor-events-title').textContent = 'Event: ' + button.dataset.studentName;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    list.innerHTML = '<p class="text-sm text-gray-500">Memuat event...</p>';

    try {
        const url = proctorEventsUrlTemplate.replace('__SESSION__', sessionId);
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) throw new Error('Event tidak dapat dimuat');
        const data = await response.json();
        list.replaceChildren();

        if (!data.events.length) {
            list.innerHTML = '<p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500">Belum ada event kamera.</p>';
            return;
        }

        data.events.forEach(event => {
            const item = document.createElement('div');
            item.className = 'flex items-start justify-between gap-4 border-b border-gray-100 py-3 last:border-0';
            const details = document.createElement('div');
            const title = document.createElement('p');
            title.className = 'text-sm font-semibold text-gray-900';
            title.textContent = event.type.replaceAll('_', ' ');
            const meta = document.createElement('p');
            meta.className = 'mt-1 text-xs text-gray-500';
            meta.textContent = `${event.source} · ${event.created_at}`;
            details.append(title, meta);
            const badge = document.createElement('span');
            badge.className = 'rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700';
            badge.textContent = event.meta ? JSON.stringify(event.meta) : 'tercatat';
            item.append(details, badge);
            list.append(item);
        });
    } catch (error) {
        list.innerHTML = '<p class="rounded-xl bg-red-50 p-4 text-sm text-red-700">Gagal memuat event. Coba lagi.</p>';
    }
}

function closeProctorEvents() {
    const modal = document.getElementById('proctor-events-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.getElementById('proctor-events-modal')?.addEventListener('click', function (event) {
    if (event.target === this) closeProctorEvents();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeProctorEvents();
});

document.addEventListener('DOMContentLoaded', function() {
    autoRefreshInterval = setInterval(refreshData, 10000);
});

document.querySelector('form')?.addEventListener('submit', function() {
    clearInterval(autoRefreshInterval);
});
</script>
@endsection
