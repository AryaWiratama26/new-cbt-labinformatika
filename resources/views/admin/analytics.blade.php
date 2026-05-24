@extends('layouts.app')

@php
$primary = '#001b6e';
$secondary = '#6b47d4';
$colors = ['#ef4444', '#f97316', '#eab308', '#22c55e'];
$chartColors = ['#001b6e', '#6b47d4', '#0891b2', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0d9488'];
@endphp

@section('content')
<div class="max-w-7xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Analitik & Grafik</h2>
            <p class="text-gray-500">Visualisasi data ujian CBT secara keseluruhan.</p>
        </div>
        <button onclick="exportAllPdf()" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-5 rounded-xl font-medium transition-colors text-sm shadow-sm">
            <i class="ph ph-file-pdf text-lg"></i> Export Semua PDF
        </button>
    </div>

    <div class="grid md:grid-cols-2 gap-8">

        {{-- 1. Score Distribution --}}
        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Distribusi Nilai</h3>
                    <p class="text-xs text-gray-500">Sebaran skor seluruh peserta ujian</p>
                </div>
                <button onclick="exportChart('scoreDistChart', 'distribusi-nilai')" class="text-sm text-primary hover:underline font-medium flex items-center gap-1 bg-[#e8eaf5] px-3 py-1.5 rounded-lg transition-colors">
                    <i class="ph ph-download-simple text-base"></i> Export
                </button>
            </div>
            <div class="relative" style="height: 260px;">
                <canvas id="scoreDistChart"></canvas>
            </div>
        </div>

        {{-- 2. Classroom Comparison --}}
        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Rata-rata per Kelas</h3>
                    <p class="text-xs text-gray-500">Perbandingan nilai rata-rata antar kelas</p>
                </div>
                <button onclick="exportChart('classroomChart', 'rata-kelas')" class="text-sm text-primary hover:underline font-medium flex items-center gap-1 bg-[#e8eaf5] px-3 py-1.5 rounded-lg transition-colors">
                    <i class="ph ph-download-simple text-base"></i> Export
                </button>
            </div>
            <div class="relative" style="height: 260px;">
                <canvas id="classroomChart"></canvas>
            </div>
        </div>

        {{-- 3. Pass/Fail per Exam --}}
        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Lulus & Gagal per Ujian</h3>
                    <p class="text-xs text-gray-500">Jumlah peserta lulus vs gagal tiap ujian</p>
                </div>
                <button onclick="exportChart('examChart', 'lulus-gagal-ujian')" class="text-sm text-primary hover:underline font-medium flex items-center gap-1 bg-[#e8eaf5] px-3 py-1.5 rounded-lg transition-colors">
                    <i class="ph ph-download-simple text-base"></i> Export
                </button>
            </div>
            <div class="relative" style="height: 260px;">
                <canvas id="examChart"></canvas>
            </div>
        </div>

        {{-- 4. Weekly Trend --}}
        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Aktivitas 30 Hari</h3>
                    <p class="text-xs text-gray-500">Jumlah ujian dikerjakan per hari</p>
                </div>
                <button onclick="exportChart('trendChart', 'trend-30-hari')" class="text-sm text-primary hover:underline font-medium flex items-center gap-1 bg-[#e8eaf5] px-3 py-1.5 rounded-lg transition-colors">
                    <i class="ph ph-download-simple text-base"></i> Export
                </button>
            </div>
            <div class="relative" style="height: 260px;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

    </div>

    {{-- 5. Overall Pass/Fail Pie --}}
    <div class="mt-8 grid md:grid-cols-3 gap-8">
        <div class="md:col-span-1 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Status Kelulusan</h3>
                    <p class="text-xs text-gray-500">Perbandingan lulus vs gagal</p>
                </div>
                <button onclick="exportChart('pieChart', 'status-kelulusan')" class="text-sm text-primary hover:underline font-medium flex items-center gap-1 bg-[#e8eaf5] px-3 py-1.5 rounded-lg transition-colors">
                    <i class="ph ph-download-simple text-base"></i> Export
                </button>
            </div>
            <div class="relative" style="height: 260px;">
                <canvas id="pieChart"></canvas>
            </div>
        </div>

        <div class="md:col-span-2 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Ringkasan</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-xs text-gray-500 mb-1">Total Dinilai</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $totalGraded }}</p>
                </div>
                <div class="p-4 bg-green-50 rounded-xl text-center">
                    <p class="text-xs text-green-600 mb-1">Lulus</p>
                    <p class="text-2xl font-bold text-green-700">{{ $passCount }}</p>
                </div>
                <div class="p-4 bg-red-50 rounded-xl text-center">
                    <p class="text-xs text-red-600 mb-1">Gagal</p>
                    <p class="text-2xl font-bold text-red-700">{{ $failCount }}</p>
                </div>
                <div class="p-4 bg-[#e8eaf5] rounded-xl text-center">
                    <p class="text-xs text-primary mb-1">Tingkat Lulus</p>
                    <p class="text-2xl font-bold text-primary">{{ $totalGraded > 0 ? round(($passCount / $totalGraded) * 100, 1) : 0 }}%</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
window.jsPDF = window.jspdf?.jsPDF || window.jsPDF;

function exportAllPdf() {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('landscape', 'mm', 'a4');
    const pageW = 280;
    const pageH = 190;
    const margin = 10;
    let y = margin;
    let pageNum = 1;

    const charts = [
        { id: 'scoreDistChart',  title: 'Distribusi Nilai' },
        { id: 'classroomChart',  title: 'Rata-rata per Kelas' },
        { id: 'examChart',       title: 'Lulus & Gagal per Ujian' },
        { id: 'trendChart',      title: 'Aktivitas 30 Hari' },
        { id: 'pieChart',        title: 'Status Kelulusan' },
    ];

    pdf.setFontSize(16);
    pdf.text('Laporan Analitik CBT', pageW / 2, y + 8, { align: 'center' });
    pdf.setFontSize(8);
    pdf.text('Digenerate: {{ now()->format('d F Y H:i') }}', pageW / 2, y + 15, { align: 'center' });
    y += 22;

    charts.forEach((chart, i) => {
        const canvas = document.getElementById(chart.id);
        if (!canvas) return;

        const imgData = canvas.toDataURL('image/png');
        const imgW = pageW - margin * 2;
        const imgH = (canvas.height / canvas.width) * imgW;

        if (y + 10 + imgH > pageH) {
            pdf.addPage();
            pageNum++;
            y = margin;
        }

        pdf.setFontSize(11);
        pdf.text(chart.title, margin, y + 4);
        y += 8;
        pdf.addImage(imgData, 'PNG', margin, y, imgW, imgH);
        y += imgH + 8;
    });

    pdf.save('laporan-analitik-cbt.pdf');
}

document.addEventListener('DOMContentLoaded', function () {
    // 1. Score Distribution
    new Chart(document.getElementById('scoreDistChart'), {
        type: 'bar',
        data: {
            labels: ['< 50', '50 – 70', '70 – 85', '> 85'],
            datasets: [{
                label: 'Jumlah Peserta',
                data: [{{ $scoreDist['below50'] }}, {{ $scoreDist['50to69'] }}, {{ $scoreDist['70to85'] }}, {{ $scoreDist['above85'] }}],
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

    // 2. Classroom Comparison
    new Chart(document.getElementById('classroomChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($classroomScores->pluck('name')) !!},
            datasets: [{
                label: 'Rata-rata Nilai',
                data: {!! json_encode($classroomScores->pluck('avg_score')) !!},
                backgroundColor: {!! json_encode($classroomScores->map(fn($c, $i) => $chartColors[$i % count($chartColors)])->values()) !!},
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => 'Rata-rata: ' + ctx.parsed.y } }
            },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#f0f2f8' } },
                x: { grid: { display: false } }
            }
        }
    });

    // 3. Pass/Fail per Exam
    new Chart(document.getElementById('examChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($exams->pluck('title')) !!},
            datasets: [
                {
                    label: 'Lulus',
                    data: {!! json_encode($exams->pluck('passed_count')) !!},
                    backgroundColor: '#22c55e',
                    borderRadius: 6,
                },
                {
                    label: 'Gagal',
                    data: {!! json_encode($exams->pluck('failed_count')) !!},
                    backgroundColor: '#ef4444',
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } }
            },
            scales: {
                x: { stacked: false, grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 45 } },
                y: { stacked: false, beginAtZero: true, grid: { color: '#f0f2f8' }, ticks: { stepSize: 1 } }
            }
        }
    });

    // 4. Activity Trend
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: {!! json_encode($dates->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))) !!},
            datasets: [{
                label: 'Ujian Dikerjakan',
                data: {!! json_encode($dates->pluck('count')) !!},
                borderColor: '{{ $primary }}',
                backgroundColor: '{{ $primary }}22',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointBackgroundColor: '{{ $primary }}',
                pointHoverRadius: 5,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ctx.parsed.y + ' ujian' } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0f2f8' }, ticks: { stepSize: 1 } },
                x: { grid: { display: false }, ticks: { font: { size: 8 }, maxTicksLimit: 15 } }
            }
        }
    });

    // 5. Overall Pass/Fail Pie
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Lulus ({{ $passCount }})', 'Gagal ({{ $failCount }})'],
            datasets: [{
                data: [{{ $passCount }}, {{ $failCount }}],
                backgroundColor: ['#22c55e', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 12, font: { size: 11 } }
                }
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
