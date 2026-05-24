@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.exams.index') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-3xl font-bold text-gray-900 mb-1">Laporan Nilai Ujian</h2>
                <p class="text-gray-500">Rekapitulasi nilai mahasiswa untuk ujian ini.</p>
            </div>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.exams.monitor', $exam) }}" class="inline-flex items-center gap-2 bg-white border border-primary/20 hover:bg-[#e8eaf5] text-primary py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-eye text-lg"></i> Monitor
            </a>
            <a href="{{ route('admin.exams.pdf', $exam) }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-file-pdf text-lg"></i> PDF
            </a>
            <a href="{{ route('admin.exams.results.csv', $exam) }}" class="inline-flex items-center gap-2 bg-secondary hover:bg-[#3d3e8a] text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-file-csv text-lg"></i> CSV
            </a>
            <button onclick="exportResultsPdf()" class="inline-flex items-center gap-2 bg-white border border-primary/20 hover:bg-[#e8eaf5] text-primary py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-file-pdf text-lg"></i> Chart PDF
            </button>
            <button onclick="window.print()" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-printer text-lg"></i> Cetak
            </button>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl"></i> {{ session('error') }}
        </div>
    @endif
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 mb-8 print:shadow-none print:border-none print:p-0">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-6 pb-6 border-b border-gray-100">
            <div>
                <span class="block text-gray-500 mb-0.5">Judul Ujian</span>
                <span class="font-bold text-gray-900">{{ $exam->title }}</span>
            </div>
            <div>
                <span class="block text-gray-500 mb-0.5">Mata Kuliah</span>
                <span class="font-bold text-gray-900">{{ $exam->course->name ?? '-' }}</span>
            </div>
            <div>
                <span class="block text-gray-500 mb-0.5">Kelas</span>
                <span class="font-bold text-gray-900">{{ $exam->classrooms->pluck('name')->implode(', ') ?: '-' }}</span>
            </div>
            <div>
                <span class="block text-gray-500 mb-0.5">Waktu Pelaksanaan</span>
                <span class="font-bold text-gray-900">{{ $exam->classrooms->isNotEmpty() ? \Carbon\Carbon::parse($exam->classrooms->sortBy(fn($c) => $c->pivot->start_time)->first()->pivot->start_time)->format('d/m/Y') : '-' }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="py-4 px-4 font-semibold text-gray-600 text-sm">No</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">NIM</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Nama Mahasiswa</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Percobaan</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Waktu Submit</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm text-right">Skor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($students as $userId => $sessions)
                        @php $first = true; $count = $sessions->count(); @endphp
                        @foreach($sessions as $session)
                        <tr class="hover:bg-gray-50/50 transition-colors {{ $session->attempt_number > 1 ? 'bg-[#e8eaf5]/30' : '' }}">
                            <td class="py-4 px-4 text-gray-500">{{ $first ? $loop->parent->iteration : '' }}</td>
                            <td class="py-4 px-6 font-mono text-gray-600">{{ $session->user->username ?? 'Dihapus' }}</td>
                            <td class="py-4 px-6 font-medium text-gray-900">
                                @if($session->user)
                                <a href="{{ route('admin.exams.student_report', [$exam, $session->user]) }}" class="hover:text-primary transition-colors">
                                    {{ $session->user->name }}
                                </a>
                                @else
                                <span class="text-gray-400 italic">Mahasiswa dihapus</span>
                                @endif
                                @if($first && $count > 1)
                                    <span class="ml-2 text-xs text-gray-500">({{ $count }}x)</span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center gap-1 text-sm">
                                    Percobaan {{ $session->attempt_number }}
                                    @if($session->attempt_number > 1)
                                        <span class="px-2 py-0.5 bg-[#eeedf7] text-secondary text-xs rounded-full font-medium">Remedial</span>
                                    @endif
                                </span>
                            </td>
                            <td class="py-4 px-6 text-sm text-gray-500">
                                {{ $session->finished_at ? $session->finished_at->format('d M Y, H:i:s') : 'Belum Selesai' }}
                            </td>
                            <td class="py-4 px-6 text-right font-bold {{ $session->score !== null && $session->score >= $exam->passing_grade ? 'text-green-600' : ($session->score !== null ? 'text-red-500' : 'text-gray-400') }}">
                                {{ $session->score ?? '-' }}
                            </td>
                        </tr>
                        @php $first = false; @endphp
                        @endforeach
                    @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-gray-500">
                            <div class="inline-flex h-16 w-16 bg-gray-50 rounded-full items-center justify-center text-gray-400 mb-4">
                                <i class="ph ph-users text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-1">Belum ada peserta</h4>
                            <p class="text-sm">Belum ada mahasiswa yang mengerjakan ujian ini.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($exam->max_attempts > 1)
        <div class="mt-6 p-4 bg-[#e8eaf5] border border-primary/20 rounded-2xl text-sm text-primary flex items-start gap-3">
            <i class="ph ph-info text-lg flex-shrink-0 mt-0.5"></i>
            <div>
                <strong>Informasi Remedial:</strong> Ujian ini memiliki nilai minimal <strong>{{ $exam->passing_grade }}</strong> dengan maksimal <strong>{{ $exam->max_attempts }}</strong> percobaan. Baris dengan latar biru adalah percobaan remedial.
            </div>
        </div>
        @endif
    </div>

    @php
        $lastSessions = collect();
        foreach ($students as $userId => $sessions) {
            $last = $sessions->where('finished_at', '!=', null)->last();
            if ($last) $lastSessions->push($last);
        }
        $totalRes = $lastSessions->count();
        $passedRes = $lastSessions->filter(fn($s) => $s->score >= $exam->passing_grade)->count();
        $failedRes = $totalRes - $passedRes;
        $scoresRes = $lastSessions->pluck('score');
        $distBelow50 = $scoresRes->filter(fn($s) => $s < 50)->count();
        $dist50to69  = $scoresRes->filter(fn($s) => $s >= 50 && $s < 70)->count();
        $dist70to85  = $scoresRes->filter(fn($s) => $s >= 70 && $s <= 85)->count();
        $distAbove85 = $scoresRes->filter(fn($s) => $s > 85)->count();
    @endphp

    <div class="grid md:grid-cols-3 gap-8 mb-8">
        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Status Kelulusan</h3>
                    <p class="text-xs text-gray-500">Percobaan terakhir tiap mahasiswa</p>
                </div>
                <button onclick="exportChart('resultsPieChart', 'status-kelulusan-{{ $exam->id }}')" class="text-sm text-primary hover:underline font-medium flex items-center gap-1 bg-[#e8eaf5] px-3 py-1.5 rounded-lg transition-colors">
                    <i class="ph ph-download-simple text-base"></i> Export
                </button>
            </div>
            <div class="relative" style="height: 220px;">
                <canvas id="resultsPieChart"></canvas>
            </div>
        </div>
        <div class="md:col-span-2 bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Distribusi Nilai</h3>
                    <p class="text-xs text-gray-500">Sebaran skor peserta ujian ini</p>
                </div>
                <button onclick="exportChart('resultsDistChart', 'distribusi-nilai-{{ $exam->id }}')" class="text-sm text-primary hover:underline font-medium flex items-center gap-1 bg-[#e8eaf5] px-3 py-1.5 rounded-lg transition-colors">
                    <i class="ph ph-download-simple text-base"></i> Export
                </button>
            </div>
            <div class="relative" style="height: 220px;">
                <canvas id="resultsDistChart"></canvas>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function exportResultsPdf() {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('landscape', 'mm', 'a4');
    const pageW = 280;
    const margin = 10;

    pdf.setFontSize(16);
    pdf.text('Laporan Nilai - {{ $exam->title }}', pageW / 2, 18, { align: 'center' });
    pdf.setFontSize(9);
    pdf.text('{{ $exam->course->name ?? '-' }} | {{ $exam->classrooms->pluck('name')->implode(', ') }}', pageW / 2, 25, { align: 'center' });

    [['resultsPieChart', 'Status Kelulusan'], ['resultsDistChart', 'Distribusi Nilai']].forEach(([id, title], i) => {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        const y = 36 + i * 100;
        pdf.setFontSize(11);
        pdf.text(title, margin, y);
        const imgData = canvas.toDataURL('image/png');
        const imgW = pageW - margin * 2;
        const imgH = (canvas.height / canvas.width) * imgW;
        pdf.addImage(imgData, 'PNG', margin, y + 4, imgW, Math.min(imgH, 80));
    });

    pdf.save('laporan-nilai-{{ $exam->id }}.pdf');
}

document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('resultsPieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Lulus ({{ $passedRes }})', 'Gagal ({{ $failedRes }})'],
            datasets: [{
                data: [{{ $passedRes }}, {{ $failedRes }}],
                backgroundColor: ['#22c55e', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } }
            }
        }
    });

    new Chart(document.getElementById('resultsDistChart'), {
        type: 'bar',
        data: {
            labels: ['< 50', '50 – 70', '70 – 85', '> 85'],
            datasets: [{
                label: 'Peserta',
                data: [{{ $distBelow50 }}, {{ $dist50to69 }}, {{ $dist70to85 }}, {{ $distAbove85 }}],
                backgroundColor: ['#ef4444', '#f97316', '#eab308', '#22c55e'],
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0f2f8' }, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });
});

function exportChart(canvasId, filename) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const link = document.createElement('a');
    link.download = filename + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}
</script>
@endpush
@endsection
