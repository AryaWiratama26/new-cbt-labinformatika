<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Nilai - {{ $exam->title }}</title>
    <style>
        @page {
            margin: 1.8cm 1.5cm 2.2cm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #222;
            line-height: 1.5;
        }

        .kop {
            width: 100%;
            margin-bottom: 18px;
            border-bottom: 2px solid #001b6e;
            padding-bottom: 14px;
        }
        .kop table {
            width: 100%;
            border-collapse: collapse;
        }
        .kop td {
            vertical-align: middle;
        }
        .kop-logo {
            width: 65px;
            text-align: center;
        }
        .kop-logo img {
            width: 55px;
        }
        .kop-text {
            padding-left: 12px;
        }
        .kop-text .univ {
            font-size: 14pt;
            font-weight: bold;
            color: #001b6e;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .kop-text .sub {
            font-size: 8pt;
            color: #555;
            margin: 1px 0 0;
        }
        .kop-text .sub2 {
            font-size: 7.5pt;
            color: #777;
            margin: 0;
        }

        .title-section {
            text-align: center;
            margin: 20px 0 16px;
        }
        .title-section h2 {
            font-size: 13pt;
            font-weight: bold;
            color: #001b6e;
            margin: 0 0 2px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .title-section p {
            font-size: 8pt;
            color: #666;
            margin: 0;
        }

        .info-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 9pt;
        }
        .info-box td {
            padding: 2px 6px;
            vertical-align: top;
        }
        .info-box .label {
            width: 100px;
            font-weight: 600;
            color: #444;
        }
        .info-box .sep {
            width: 12px;
            color: #999;
        }

        .ringkasan {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 8.5pt;
        }
        .ringkasan td {
            padding: 6px 10px;
            text-align: center;
            border: 1px solid #d0d0d0;
            width: 16.66%;
        }
        .ringkasan .nilai {
            font-size: 14pt;
            font-weight: bold;
            color: #001b6e;
            margin-top: 1px;
        }
        .ringkasan .lbl {
            color: #666;
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        table.data thead th {
            background: #e8eaf5;
            color: #001b6e;
            padding: 7px 8px;
            text-align: left;
            font-size: 8pt;
            font-weight: 700;
            border: 1px solid #c5c9de;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.data tbody td {
            padding: 5px 8px;
            border: 1px solid #ddd;
        }
        table.data tbody tr:nth-child(even) {
            background: #fafafe;
        }
        .status-lulus {
            font-weight: bold;
            color: #16a34a;
        }
        .status-gagal {
            font-weight: bold;
            color: #dc2626;
        }
        .badge-remed {
            display: inline-block;
            font-size: 6.5pt;
            padding: 1px 5px;
            border: 1px solid #c5c9de;
            color: #4f50a8;
            margin-left: 3px;
            font-weight: 600;
        }

        .footer {
            position: fixed;
            bottom: -1.6cm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7pt;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
        .footer .page:after {
            content: counter(page);
        }

        .ttd-section {
            margin-top: 30px;
            text-align: right;
            font-size: 9pt;
        }
        .ttd-section .nama {
            margin-top: 70px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="kop">
        <table>
            <tr>
                <td class="kop-logo">
                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAASwAAADkCAMAAAAVb+kqAAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAAHyUExURQ8DfAACggACggACggACggACggACggACggICgQACggACggACggACggACggACggACggACggACggACggACggACggACggACggACggACggACggACggACggACggACggACguUhKeUhKeYhKeYhKeYhKQACguYhKeUhKeUhKeUhKQACguYhKQACguYhKeYhKQACguYhKeYhKeUhKeYhKeYhKeYhKeUhKeYhKeUhKeUgKeYhKeYhKeYhKeYhKeYgKeYhKeUhKeYhKeUhKQACguUhKQACguUhKeQgKQACguUhKQACggACguYhKeUhKeYhKQACguYhKeUhKeYhKeYhKeYhKeYhKQACguUhKQACguYhKQACggACggACggACguYhKQcIhQkLhx0ekBkbjxIUiwwOiA8RiRcZjT0+oF5fsGtorXN0u4OEwouMxoqLxn5/wGRls0dIpCIkk3d4vLe43NPU6vDw9/7+/vT0+t/g8MPD4pOUyk1PqB8gkePj8c/Q6L+/4MvL5ezs9vf4+y8wmaus1l9gsOfo9JeYzGprtikrlkpMppqbzsvL5i0umDM1m29wuI+QyE9Qqa+w2G5vuKyt1ru83ldYrdzc7rO02icolUNFozc4naOk0p+g0D9AoX+AwFJUqp6f0Fpbrnp7vjo8n////6dufkkAAABddFJOUwATVGU0I15DC77+3XTt6X72xIOTtNSjnsnjP/30+SsfJX6PtsEb1ypMbA+Xbr3JO8PjsPL2xhSkHRlgWETrEOgh3jtOU5lkDFqDS4raM6wvXmk40vX4qpWOztlqzZd8vG8AAAABYktHRKUuuUovAAAACXBIWXMAAC4jAAAuIwF4pT92AAAAB3RJTUUH6QgRCyAWSE/lwwAAK1VJREFUeNrtffl/G8eRX/X0DKZQMwBmeg5g0APAq6xtLZPs5vRaL9cm603y1jn2iN/b3bc9IEzalCzZEi0SlGTrIKOIlmQ53sTWYSmJHecPfT/MCRCSKH8cElJY/sGkAAKYL6qrv/Wt6hqAAzuwAzuwAzuwAzuwAzuwAzuwAzuwAzuwAzuwAzuwAzuwA3vyjWmcc41zHQDAqGmm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" alt="Logo UPB">
                </td>
                <td class="kop-text">
                    <div class="univ">UNIVERSITAS PELITA BANGSA</div>
                    <div class="sub">LABORATORIUM INFORMATIKA – CBT PRAKTIKUM</div>
                    <div class="sub2">Jl. Inspeksi Kalimalang Tegal Danas, Cikarang Pusat, Kab. Bekasi</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title-section">
        <h2>Laporan Nilai Ujian</h2>
        <p>{{ $exam->title }} — {{ $exam->course->name ?? '-' }} — Kelas {{ $exam->classroom->name ?? '-' }}</p>
    </div>

    <table class="info-box">
        <tr>
            <td class="label">Mata Kuliah</td>
            <td class="sep">:</td>
            <td>{{ $exam->course->name ?? '-' }} ({{ $exam->course->code ?? '-' }})</td>
            <td class="label">Tanggal</td>
            <td class="sep">:</td>
            <td>{{ $exam->start_time->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Kelas</td>
            <td class="sep">:</td>
            <td>{{ $exam->classroom->name ?? '-' }}</td>
            <td class="label">Nilai Minimal</td>
            <td class="sep">:</td>
            <td>{{ $exam->passing_grade }}</td>
        </tr>
        <tr>
            <td class="label">Dosen Pengampu</td>
            <td class="sep">:</td>
            <td>-</td>
            <td class="label">Jumlah Soal</td>
            <td class="sep">:</td>
            <td>{{ $exam->getQuestionsCount() }}</td>
        </tr>
    </table>

    <table class="ringkasan">
        <tr>
            <td>
                <div class="lbl">Peserta</div>
                <div class="nilai">{{ $totalStudents }}</div>
            </td>
            <td>
                <div class="lbl">Rata-rata</div>
                <div class="nilai">{{ $avgScore }}</div>
            </td>
            <td>
                <div class="lbl">Tertinggi</div>
                <div class="nilai">{{ $highest }}</div>
            </td>
            <td>
                <div class="lbl">Terendah</div>
                <div class="nilai">{{ $lowest }}</div>
            </td>
            <td>
                <div class="lbl">Lulus</div>
                <div class="nilai" style="color:#16a34a">{{ $passed }}</div>
            </td>
            <td>
                <div class="lbl">Tidak Lulus</div>
                <div class="nilai" style="color:#dc2626">{{ $failed }}</div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:30px;text-align:center">No</th>
                <th style="width:70px">NIM</th>
                <th>Nama Mahasiswa</th>
                <th style="width:50px;text-align:center">Percobaan</th>
                <th style="width:46px;text-align:center">Nilai</th>
                <th style="width:64px;text-align:center">Status</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @forelse($students as $userId => $sessions)
                @foreach($sessions as $session)
                    <tr>
                        <td style="text-align:center">{{ $loop->first ? $no : '' }}</td>
                        <td>{{ $session->user->username ?? 'Dihapus' }}</td>
                        <td>
                            {{ $session->user->name ?? 'Mahasiswa Dihapus' }}
                            @if($session->attempt_number > 1)
                                <span class="badge-remed">Remedial</span>
                            @endif
                        </td>
                        <td style="text-align:center">{{ $session->attempt_number }}</td>
                        <td style="text-align:center;font-weight:bold">{{ $session->score ?? '-' }}</td>
                        <td style="text-align:center">
                            @if($session->score !== null)
                                <span class="{{ $session->score >= $exam->passing_grade ? 'status-lulus' : 'status-gagal' }}">
                                    {{ $session->score >= $exam->passing_grade ? 'LULUS' : 'GAGAL' }}
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
                @php if($sessions->isNotEmpty()) $no++; @endphp
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#999;padding:20px">Belum ada peserta yang mengerjakan ujian.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="ttd-section">
        <p>Bekasi, {{ now()->format('d F Y') }}</p>
        <p>Dosen Pengampu,</p>
        <div class="nama">( ______________________________ )</div>
    </div>

    <div class="footer">
        Laporan digenerate oleh Sistem CBT Praktikum – Universitas Pelita Bangsa &bull; Halaman <span class="page"></span>
    </div>

</body>
</html>