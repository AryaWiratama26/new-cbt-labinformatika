@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-1">Riwayat Ujian</h2>
        <p class="text-gray-500">Semua ujian yang pernah Anda kerjakan.</p>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl"></i>
            {{ session('error') }}
        </div>
    @endif

    @if($sessions->count() > 0)
        <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 p-6 md:p-8 mb-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <i class="ph ph-chart-bar text-primary"></i>
                Grafik Nilai
            </h3>
            <div class="relative" style="height: 260px;">
                <canvas id="scoreChart"></canvas>
            </div>
        </div>

        @php
            $chartLabels = [];
            $chartScores = [];
            $chartColors = [];
            foreach ($sessions as $s) {
                $title = mb_strlen($s->exam->title) > 22 ? mb_substr($s->exam->title, 0, 20) . '...' : $s->exam->title;
                $chartLabels[] = $title;
                $chartScores[] = (float) ($s->score ?? 0);
                $pg = (int) ($s->exam->passing_grade ?? 70);
                $chartColors[] = ($s->score ?? 0) >= $pg ? '#22c55e' : '#ef4444';
            }
        @endphp

        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('scoreChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode($chartLabels) !!},
                    datasets: [{
                        label: 'Nilai',
                        data: {!! json_encode($chartScores) !!},
                        backgroundColor: {!! json_encode($chartColors) !!},
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return 'Nilai: ' + ctx.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: '#f0f2f8' },
                            ticks: { font: { size: 11 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10 } }
                        }
                    }
                }
            });
        });
        </script>
        @endpush

        <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left py-4 px-6 text-xs font-semibold text-gray-500 uppercase tracking-wider">No</th>
                            <th class="text-left py-4 px-6 text-xs font-semibold text-gray-500 uppercase tracking-wider">Mata Kuliah</th>
                            <th class="text-left py-4 px-6 text-xs font-semibold text-gray-500 uppercase tracking-wider">Judul Ujian</th>
                            <th class="text-center py-4 px-6 text-xs font-semibold text-gray-500 uppercase tracking-wider">Percobaan</th>
                            <th class="text-center py-4 px-6 text-xs font-semibold text-gray-500 uppercase tracking-wider">Skor</th>
                            <th class="text-center py-4 px-6 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-left py-4 px-6 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="text-center py-4 px-6 text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sessions as $session)
                            @php
                                $isPassed = ($session->score ?? 0) >= ($session->exam->passing_grade ?? 70);
                            @endphp
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-4 px-6 text-sm text-gray-500">{{ $loop->iteration }}</td>
                                <td class="py-4 px-6 text-sm font-medium text-gray-900">{{ $session->exam->course->name ?? '-' }}</td>
                                <td class="py-4 px-6 text-sm text-gray-700">{{ $session->exam->title }}</td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-primary/10 text-primary text-xs font-bold">
                                        {{ $session->attempt_number }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="text-lg font-bold {{ $isPassed ? 'text-green-600' : 'text-red-500' }}">
                                        {{ $session->score ?? '-' }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    @if($isPassed)
                                        <span class="px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full border border-green-200">Lulus</span>
                                    @else
                                        <span class="px-3 py-1 bg-red-50 text-red-700 text-xs font-semibold rounded-full border border-red-200">Tidak Lulus</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-sm text-gray-500">
                                    {{ $session->finished_at ? $session->finished_at->format('d M Y H:i') : '-' }}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <a href="{{ route('student.history.review', $session) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary-hover transition-colors">
                                        <i class="ph ph-eye text-base"></i>
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">
            {{ $sessions->links() }}
        </div>
    @else
        <div class="text-center py-16 bg-white rounded-[2rem] border border-gray-100">
            <div class="inline-flex h-20 w-20 bg-gray-50 rounded-full items-center justify-center text-gray-400 mb-4">
                <i class="ph ph-clock-counter-clockwise text-4xl"></i>
            </div>
            <h4 class="text-xl font-bold text-gray-900 mb-2">Belum ada riwayat ujian</h4>
            <p class="text-gray-500">Anda belum menyelesaikan ujian apapun.</p>
            <a href="{{ route('student.dashboard') }}" class="inline-flex items-center gap-2 mt-6 bg-primary hover:bg-primary-hover text-white py-3 px-6 rounded-xl font-medium transition-colors">
                <i class="ph ph-arrow-left text-lg"></i>
                Ke Dashboard
            </a>
        </div>
    @endif
</div>
@endsection
