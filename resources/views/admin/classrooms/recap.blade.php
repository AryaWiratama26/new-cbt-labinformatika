@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4 justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.classrooms') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
                <i class="ph ph-arrow-left text-xl"></i>
            </a>
            <div>
                <h2 class="text-3xl font-bold text-gray-900 mb-1">Rekap Nilai Kelas</h2>
                <p class="text-gray-500">{{ $classroom->name }} — {{ $classroom->academic_year ?? 'Tahun tidak diatur' }} {{ $classroom->semester ?? '' }}</p>
            </div>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.classrooms.recap.csv', $classroom) }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-2.5 px-4 rounded-xl font-medium transition-colors text-sm">
                <i class="ph ph-download-simple"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
        @if($exams->isEmpty())
            <div class="text-center py-12 text-gray-500">
                <i class="ph ph-chart-bar text-4xl text-gray-300 mb-3"></i>
                <p class="font-medium">Belum ada ujian untuk kelas ini.</p>
                <p class="text-sm">Buat ujian terlebih dahulu untuk melihat rekap nilai.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[600px] text-sm">
                    <thead>
                        <tr class="bg-primary text-white">
                            <th class="py-3 px-4 font-semibold text-xs sticky left-0 bg-primary">No</th>
                            <th class="py-3 px-4 font-semibold text-xs sticky left-0 bg-primary">NIM</th>
                            <th class="py-3 px-4 font-semibold text-xs sticky left-0 bg-primary min-w-[150px]">Nama</th>
                            @foreach($exams as $exam)
                                <th class="py-3 px-3 font-semibold text-xs text-center min-w-[100px]">
                                    {{ $exam->title }}
                                    <span class="block text-[10px] font-normal opacity-75">{{ $exam->course->code }}</span>
                                </th>
                            @endforeach
                            <th class="py-3 px-4 font-semibold text-xs text-center bg-primary">Rata-rata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($students as $i => $student)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-3 px-4 text-gray-500 text-xs sticky left-0 bg-white">{{ $i + 1 }}</td>
                                <td class="py-3 px-4 font-mono text-gray-600 text-xs sticky left-0 bg-white">{{ $student->username }}</td>
                                <td class="py-3 px-4 font-medium text-gray-900 sticky left-0 bg-white">{{ $student->name }}</td>
                                @php $rowTotal = 0; $rowCount = 0; @endphp
                                @foreach($exams as $exam)
                                    @php
                                        $key = $student->id . '_' . $exam->id;
                                        $session = $sessions->get($key);
                                        $score = null;
                                        if ($session) {
                                            $lastAttempt = $lastAttempts->get($key);
                                            $lastSession = $session->firstWhere('attempt_number', $lastAttempt->max_attempt ?? 1);
                                            $score = $lastSession && $lastSession->finished_at ? $lastSession->score : null;
                                        }
                                        if (is_numeric($score)) {
                                            $rowTotal += $score;
                                            $rowCount++;
                                        }
                                    @endphp
                                    <td class="py-3 px-3 text-center">
                                        @if($score !== null)
                                            <span class="font-bold {{ $score >= $exam->passing_grade ? 'text-green-600' : 'text-red-500' }}">
                                                {{ $score }}
                                            </span>
                                        @else
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-3 px-4 text-center font-bold text-primary">
                                    {{ $rowCount > 0 ? round($rowTotal / $rowCount, 1) : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + $exams->count() }}" class="py-12 text-center text-gray-500">
                                    Belum ada mahasiswa di kelas ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection