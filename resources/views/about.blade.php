@extends('layouts.app')

@section('title', 'Tentang CBT Praktikum — Universitas Pelita Bangsa')

@section('content')
{{-- Halaman ini sengaja tidak memakai tanda dolar di teks: layout memasang KaTeX
     auto-render yang menganggap pasangan $...$ sebagai rumus. --}}
<script>document.documentElement.classList.add('js');</script>

<style>
    /* ── Reveal saat scroll (hanya aktif bila JS jalan) ── */
    .js .reveal { opacity: 0; transform: translateY(14px); transition: opacity .6s ease, transform .6s cubic-bezier(.2,.7,.2,1); transition-delay: var(--d, 0ms); }
    .js .reveal.in { opacity: 1; transform: none; }

    /* ── Judul hero: tiap baris naik dari bawah garis ── */
    .hero-line { display: block; transform: translateY(105%); animation: hero-up .8s cubic-bezier(.2,.7,.2,1) forwards; animation-delay: var(--d, 0ms); }
    @keyframes hero-up { to { transform: translateY(0); } }
    .hero-fade { opacity: 0; animation: hero-fade .7s ease forwards; animation-delay: var(--d, 0ms); }
    @keyframes hero-fade { to { opacity: 1; } }

    /* ── Contoh tampilan ujian ── */
    .demo-cell { transition: background-color .35s ease, color .35s ease, border-color .35s ease, transform .35s ease; }
    .demo-cell.is-current { transform: translateY(-2px); }
    .demo-opt { transition: border-color .25s ease, background-color .25s ease, box-shadow .25s ease; }
    .demo-swap { transition: opacity .2s ease; }
    .demo-swap.out { opacity: 0; }

    /* ── Garis alur ujian ── */
    .tl-fill { transform-origin: top; transform: scaleY(0); }
    .tl-dot { transition: background-color .4s ease, color .4s ease, border-color .4s ease, transform .4s ease; }
    .tl-step.active .tl-dot { background: var(--color-primary); color: #fff; border-color: var(--color-primary); transform: scale(1.08); }
    .js .tl-step .tl-body { transition: opacity .5s ease; opacity: .45; }
    .js .tl-step.active .tl-body { opacity: 1; }

    /* ── Editor bahasa ── */
    .code-swap { transition: opacity .25s ease, transform .25s ease; }
    .code-swap.out { opacity: 0; transform: translateY(4px); }
    .caret { display: inline-block; width: .5ch; height: 1.1em; vertical-align: -.2em; background: #c7c9ee; animation: caret 1s steps(1) infinite; }
    @keyframes caret { 50% { opacity: 0; } }

    /* ── Log kejadian kamera ── */
    .log-in { animation: log-in .45s cubic-bezier(.2,.7,.2,1); }
    @keyframes log-in { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: none; } }
    .pulse-dot { animation: pulse-dot 2s ease-out infinite; }
    @keyframes pulse-dot { 0% { box-shadow: 0 0 0 0 rgba(79,80,168,.45); } 70%, 100% { box-shadow: 0 0 0 8px rgba(79,80,168,0); } }

    /* ── Baris spesifikasi ── */
    .spec-row { transition: background-color .2s ease; }
    .spec-row:hover { background-color: #f8f9fc; }

    @media (prefers-reduced-motion: reduce) {
        .js .reveal { opacity: 1; transform: none; transition: none; }
        .hero-line, .hero-fade { animation: none; transform: none; opacity: 1; }
        .pulse-dot, .caret, .log-in { animation: none; }
        .tl-fill { transform: scaleY(1); }
        .js .tl-step .tl-body { opacity: 1; }
    }
</style>

@php
    $ctaHref = auth()->check()
        ? (auth()->user()->role === 'admin' ? route('admin.dashboard') : route('student.dashboard'))
        : route('login');
    $ctaLabel = auth()->check() ? 'Buka dashboard' : 'Masuk ke CBT';
@endphp

{{-- Navigasi untuk tamu. Pengguna yang sudah login memakai navigasi bawaan layout. --}}
@guest
<nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
<div class="max-w-[90rem] mx-auto w-full px-6 md:px-12 py-4 flex justify-between items-center">
    <a href="{{ route('about') }}" class="flex items-center gap-3">
        <img src="{{ asset('images/logo-upb.png') }}" alt="Logo Universitas Pelita Bangsa" class="h-8 object-contain">
        <span>
            <span class="block font-bold text-gray-900 leading-tight">CBT Praktikum</span>
            <span class="block text-xs text-gray-500">Universitas Pelita Bangsa</span>
        </span>
    </a>
    <div class="flex items-center gap-6">
        <div class="hidden lg:flex items-center gap-6 text-sm font-medium text-gray-600">
            <a href="#tentang" class="hover:text-primary transition-colors">Tentang</a>
            <a href="#alur" class="hover:text-primary transition-colors">Alur ujian</a>
            <a href="#fitur" class="hover:text-primary transition-colors">Fitur</a>
            <a href="#kamera" class="hover:text-primary transition-colors">Kamera</a>
            <a href="#spesifikasi" class="hover:text-primary transition-colors">Spesifikasi</a>
        </div>
        <a href="{{ $ctaHref }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white text-sm font-medium px-5 py-2.5 rounded-xl transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
            {{ $ctaLabel }} <i class="ph ph-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</div>
</nav>
@endguest

{{-- ═════════ HERO ═════════ --}}
<section class="min-h-[calc(100svh-4.5rem)] flex items-center">
  <div class="max-w-[90rem] mx-auto w-full px-6 md:px-12 py-14 md:py-16">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-12 md:gap-16 items-center">
        <div>
            <div class="flex items-center gap-3 mb-6 hero-fade" style="--d: 0ms">
                <div class="bg-white inline-flex p-3 rounded-2xl shadow-sm">
                    <img src="{{ asset('images/logo-upb.png') }}" alt="Logo Universitas Pelita Bangsa" class="h-12 w-12 object-contain">
                </div>
                <div class="bg-white inline-flex p-3 rounded-2xl shadow-sm">
                    <img src="{{ asset('images/logo-aslab.jpeg') }}" alt="Logo Asisten Laboratorium Informatika" class="h-12 w-12 object-contain">
                </div>
            </div>
            <p class="text-sm font-semibold text-gray-500 tracking-wider uppercase mb-2 hero-fade" style="--d: 80ms">Laboratorium Informatika · Universitas Pelita Bangsa</p>
            <h1 class="text-5xl md:text-6xl xl:text-7xl font-extrabold text-gray-900 leading-[1.08] mb-5 tracking-tight">
                <span class="block overflow-hidden pb-1"><span class="hero-line" style="--d: 120ms">Ujian praktikum,</span></span>
                <span class="block overflow-hidden pb-1"><span class="hero-line" style="--d: 220ms">langsung dari</span></span>
                <span class="block overflow-hidden pb-1"><span class="hero-line text-primary" style="--d: 320ms">browser.</span></span>
            </h1>
            <p class="text-gray-600 text-lg xl:text-xl leading-relaxed max-w-md xl:max-w-lg mb-8 hero-fade" style="--d: 480ms">
                CBT Praktikum dipakai untuk menjadwalkan, mengawasi, dan menilai ujian praktikum, dari soal pilihan ganda sampai kode program yang dijalankan langsung.
            </p>
            <div class="flex flex-wrap items-center gap-3 hero-fade" style="--d: 600ms">
                <a href="{{ $ctaHref }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white py-3.5 px-6 rounded-xl font-medium transition-colors shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    {{ $ctaLabel }} <i class="ph ph-arrow-right font-bold" aria-hidden="true"></i>
                </a>
                <a href="#alur" class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 text-gray-700 py-3.5 px-6 rounded-xl font-medium transition-colors border border-gray-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    Lihat cara kerjanya <i class="ph ph-arrow-down" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        {{-- Contoh tampilan ujian: memperagakan status simpan yang sama dengan halaman ujian asli --}}
        <div class="hero-fade w-full max-w-xl md:justify-self-end" style="--d: 360ms">
            <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-xl shadow-gray-200/50 border border-gray-100" role="img" aria-label="Contoh tampilan halaman ujian: soal pilihan ganda, penghitung waktu, dan status penyimpanan jawaban.">
                <div class="flex items-center justify-between gap-3 mb-6">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-10 w-10 shrink-0 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <i class="ph-fill ph-student text-xl" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-900 text-sm truncate">Praktikum Pemrograman Web</p>
                            <p class="text-xs text-gray-500">Contoh tampilan ujian</p>
                        </div>
                    </div>
                    <div class="shrink-0 bg-red-50 border border-red-200 text-red-700 px-3 py-1.5 rounded-xl flex items-center gap-2 font-mono font-bold text-sm">
                        <i class="ph ph-timer text-base" aria-hidden="true"></i>
                        <span id="demo-timer">59:48</span>
                    </div>
                </div>

                <div id="demo-question" class="demo-swap">
                    <p class="text-gray-900 font-medium leading-relaxed mb-3 min-h-[3.25rem]">
                        <span class="text-primary font-bold mr-1" data-q-num>4.</span><span data-q-text>Tag HTML mana yang dipakai untuk membuat tautan?</span>
                    </p>
                    <div class="space-y-2.5" id="demo-options">
                        @foreach(['&lt;a&gt;', '&lt;link&gt;', '&lt;href&gt;', '&lt;url&gt;'] as $opt)
                        <div class="demo-opt flex items-center p-3.5 border border-gray-200 rounded-xl">
                            <span class="w-4 h-4 rounded-full border border-gray-300 shrink-0 flex items-center justify-center" data-radio></span>
                            <span class="ml-3 text-sm text-gray-700 font-mono" data-opt>{!! $opt !!}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-gray-100">
                    <div class="grid grid-cols-10 gap-1.5 mb-4" id="demo-nav" aria-hidden="true">
                        @for($i = 1; $i <= 10; $i++)
                        <span class="demo-cell h-7 rounded-md border text-[11px] font-semibold flex items-center justify-center {{ $i <= 3 ? 'bg-primary text-white border-primary' : 'bg-white text-gray-500 border-gray-200' }}">{{ $i }}</span>
                        @endfor
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-xs">
                        <p id="demo-status" class="font-medium text-primary flex items-center gap-1.5" aria-live="off">
                            <i class="ph-bold ph-check" aria-hidden="true"></i> <span>Tersimpan di server</span>
                        </p>
                        <div class="flex items-center gap-3 text-gray-500">
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary"></span>Server</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-400"></span>Lokal</span>
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-white border border-gray-300"></span>Kosong</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>
</section>

{{-- ═════════ TENTANG ═════════ --}}
<section id="tentang" class="bg-white border-y border-gray-100">
    <div class="max-w-[90rem] mx-auto w-full px-6 md:px-12 py-16 md:py-24 grid grid-cols-1 md:grid-cols-5 gap-10 md:gap-16">
        <div class="md:col-span-2 reveal">
            <p class="text-sm font-semibold text-gray-500 tracking-wider uppercase mb-2">Tentang</p>
            <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight leading-tight">Dibuat untuk ujian yang isinya kode, bukan hanya pilihan ganda.</h2>
        </div>
        <div class="md:col-span-3 space-y-5 text-gray-600 text-lg leading-relaxed">
            <p class="reveal" style="--d: 80ms">Ujian praktikum meminta mahasiswa menulis program, menjelaskan dengan uraian, dan menjawab soal teori, sering kali untuk banyak kelas sekaligus. CBT Praktikum mengurus ketiganya dalam satu tempat: bank soal per modul, jadwal per kelas, dan penilaian yang bisa ditinjau ulang.</p>
            <p class="reveal" style="--d: 160ms">Sistem ini dikembangkan oleh Laboratorium Informatika Universitas Pelita Bangsa untuk kebutuhan ujian praktikum di laboratorium itu sendiri, bukan produk umum yang diadaptasi belakangan.</p>
            <div class="reveal grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3" style="--d: 240ms">
                <div class="flex items-center gap-4 p-4 rounded-2xl bg-[#f0f2f8]">
                    <img src="{{ asset('images/logo-upb.png') }}" alt="" class="h-12 w-12 object-contain shrink-0">
                    <div>
                        <p class="font-bold text-gray-900 text-base leading-tight">Universitas Pelita Bangsa</p>
                        <p class="text-sm text-gray-500">Laboratorium Informatika</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-4 rounded-2xl bg-[#f0f2f8]">
                    <img src="{{ asset('images/logo-aslab.jpeg') }}" alt="" class="h-12 w-12 object-contain shrink-0 mix-blend-multiply">
                    <div>
                        <p class="font-bold text-gray-900 text-base leading-tight">Asisten Laboratorium</p>
                        <p class="text-sm text-gray-500">Informatic Engineering Lab Assistant</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═════════ ALUR UJIAN ═════════ --}}
<section id="alur" class="max-w-[90rem] mx-auto w-full px-6 md:px-12 py-16 md:py-24">
    <div class="max-w-xl mb-12 reveal">
        <p class="text-sm font-semibold text-gray-500 tracking-wider uppercase mb-2">Alur ujian</p>
        <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight leading-tight">Dari bank soal sampai laporan nilai, empat langkah.</h2>
    </div>

    <ol class="relative max-w-3xl" data-timeline>
        <div class="absolute left-5 top-3 bottom-3 w-px bg-gray-200" aria-hidden="true"></div>
        <div class="tl-fill absolute left-5 top-3 bottom-3 w-px bg-primary" aria-hidden="true" data-fill></div>

        @php
            $steps = [
                ['Siapkan bank soal', 'Admin', 'Tambah soal satu per satu, atau impor dari CSV, DOCX, atau ZIP beserta gambarnya. Pilihan ganda, koding, dan esai disimpan per modul praktikum.'],
                ['Jadwalkan per kelas', 'Admin', 'Satu ujian bisa dipakai banyak kelas, masing-masing dengan waktu mulai, waktu selesai, durasi, dan PIN sendiri. Layar penuh, batas pindah tab, dan kamera diatur per ujian.'],
                ['Mahasiswa mengerjakan', 'Mahasiswa', 'Masuk dengan NIM, memasukkan PIN, lalu mengerjakan. Jawaban tersimpan otomatis. Bila koneksi putus, jawaban ditahan di perangkat dan dikirim ulang saat tersambung.'],
                ['Nilai dan laporan', 'Admin', 'Pilihan ganda dinilai otomatis. Koding bisa dinilai dengan bantuan AI, esai dinilai manual. Hasilnya bisa diunduh sebagai PDF atau CSV, atau dibaca sistem lain lewat API.'],
            ];
        @endphp
        @foreach($steps as $i => [$title, $who, $text])
        <li class="tl-step relative pl-16 {{ $loop->last ? '' : 'pb-12' }}" data-step>
            <span class="tl-dot absolute left-0 top-0 h-10 w-10 rounded-full bg-white border-2 border-gray-200 text-gray-400 font-bold flex items-center justify-center">{{ $i + 1 }}</span>
            <div class="tl-body">
                <div class="flex flex-wrap items-center gap-3 mb-1.5">
                    <h3 class="text-xl font-bold text-gray-900">{{ $title }}</h3>
                    <span class="px-2.5 py-0.5 bg-white text-gray-600 text-xs font-semibold rounded-full border border-gray-200">{{ $who }}</span>
                </div>
                <p class="text-gray-600 leading-relaxed max-w-xl">{{ $text }}</p>
            </div>
        </li>
        @endforeach
    </ol>
</section>

{{-- ═════════ FITUR ═════════ --}}
<section id="fitur" class="max-w-[90rem] mx-auto w-full px-6 md:px-12 pb-16 md:pb-24">
    <div class="max-w-xl mb-12 reveal">
        <p class="text-sm font-semibold text-gray-500 tracking-wider uppercase mb-2">Fitur</p>
        <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight leading-tight">Yang dikerjakan sistem selama ujian berjalan.</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
        {{-- Jenis soal + editor --}}
        <div class="md:col-span-3 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 reveal">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-primary/10 text-primary mb-5">
                <i class="ph ph-code text-2xl" aria-hidden="true"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Tiga jenis soal, termasuk kode yang bisa dijalankan</h3>
            <p class="text-gray-600 leading-relaxed mb-6">Pilihan ganda dan esai seperti biasa. Untuk soal koding, mahasiswa menulis di editor, menekan Jalankan, dan melihat output atau pesan error sebelum mengumpulkan. Program yang meminta input ditanyai langsung di kotak input.</p>

            <div class="rounded-2xl overflow-hidden border border-gray-800 shadow-sm" role="img" aria-label="Contoh editor kode yang berganti antar bahasa pemrograman">
                <div class="flex items-center justify-between gap-3 bg-gray-900 px-4 py-2.5">
                    <div class="flex items-center gap-2" aria-hidden="true">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-yellow-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-green-400"></span>
                    </div>
                    <div class="flex flex-wrap justify-end gap-1" id="lang-chips">
                        @foreach(['Python', 'PHP', 'JavaScript', 'C / C++', 'Java', 'SQL'] as $lang)
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-medium text-gray-400 transition-colors" data-lang-chip>{{ $lang }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="bg-gray-950 text-gray-100 font-mono text-xs leading-6 p-4 min-h-[5.5rem]">
                    <div class="code-swap" id="code-body"><span class="text-gray-100">for i in range(1, 4):</span><br><span class="text-gray-100">&nbsp;&nbsp;&nbsp;&nbsp;print("Soal", i)</span><span class="caret" aria-hidden="true"></span></div>
                </div>
                <div class="bg-gray-900 border-t border-gray-800 px-4 py-3 font-mono text-xs">
                    <span class="text-gray-500">Output</span>
                    <div class="code-swap text-gray-100 mt-1" id="code-out">Soal 1<br>Soal 2<br>Soal 3</div>
                </div>
            </div>
        </div>

        {{-- Selama ujian --}}
        <div class="md:col-span-2 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 reveal" style="--d: 100ms">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-primary/10 text-primary mb-5">
                <i class="ph ph-shield-check text-2xl" aria-hidden="true"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-4">Pengaturan ujian</h3>
            <ul class="space-y-3.5 text-sm text-gray-600 leading-relaxed">
                @foreach([
                    'Urutan soal dan pilihan jawaban diacak berbeda untuk tiap mahasiswa.',
                    'PIN per kelas, dibagikan pengawas di ruangan.',
                    'Layar penuh bisa diwajibkan. Keluar dari layar penuh dihitung sebagai pindah tab.',
                    'Batas pindah tab: bila terlampaui, ujian otomatis dikumpulkan.',
                    'Nilai dihitung server dari jawaban yang tersimpan, bukan dari data kiriman browser.',
                ] as $item)
                <li class="flex gap-3"><i class="ph-fill ph-check-circle text-primary text-lg shrink-0 mt-px" aria-hidden="true"></i><span>{{ $item }}</span></li>
                @endforeach
            </ul>
        </div>

        {{-- Remedial --}}
        <div class="md:col-span-2 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 reveal">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-primary/10 text-primary mb-5">
                <i class="ph ph-arrows-clockwise text-2xl" aria-hidden="true"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Remedial dan nilai</h3>
            <p class="text-gray-600 leading-relaxed text-sm">Admin menetapkan passing grade dan jumlah percobaan. Mahasiswa yang belum lulus bisa mengulang sampai batasnya. Angka nilai boleh disembunyikan dari mahasiswa; status lulus atau gagal dan alur remedial tetap berjalan.</p>
        </div>

        {{-- Laporan --}}
        <div class="md:col-span-3 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 reveal" style="--d: 100ms">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-primary/10 text-primary mb-5">
                <i class="ph ph-file-pdf text-2xl" aria-hidden="true"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Laporan yang siap dicetak atau dibaca sistem lain</h3>
            <p class="text-gray-600 leading-relaxed mb-5">Hasil ujian diekspor sebagai PDF dengan kop surat, atau CSV. Rekap per kelas menampilkan semua ujian dalam satu matriks. Sistem kampus lain dapat mengambil nilai lewat API baca-saja dengan kunci API yang dikelola admin.</p>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                @foreach(['PDF berkop surat', 'CSV hasil ujian', 'Rekap per kelas', 'API nilai (baca-saja)'] as $tag)
                <span class="px-3 py-1 bg-[#e8eaf5] text-primary rounded-full">{{ $tag }}</span>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═════════ KAMERA ═════════ --}}
<section id="kamera" class="bg-white border-y border-gray-100">
    <div class="max-w-[90rem] mx-auto w-full px-6 md:px-12 py-16 md:py-24 grid grid-cols-1 md:grid-cols-5 gap-10 md:gap-14 items-center">
        <div class="md:col-span-3 reveal">
            <div class="rounded-[2rem] overflow-hidden border border-gray-100 bg-[#fbfaf7]">
                <img src="{{ asset('images/dual-camera-animation.webp') }}" alt="Mahasiswa mengerjakan ujian di laptop, dengan webcam di atas laptop dan HP di tripod yang merekam dari samping." class="w-full h-auto" loading="lazy" width="1672" height="941">
            </div>
        </div>
        <div class="md:col-span-2">
            <div class="reveal">
                <p class="text-sm font-semibold text-gray-500 tracking-wider uppercase mb-2">Pengawasan kamera</p>
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight leading-tight mb-4">Opsional per ujian, dan hanya mencatat kejadian.</h2>
                <p class="text-gray-600 leading-relaxed mb-3">Kamera laptop memantau wajah. Kamera HP, disambungkan lewat QR dan diletakkan di samping meja, memantau area kerja. Pengenalan berjalan di browser masing-masing; server hanya menerima nama kejadiannya, tanpa gambar dan tanpa rekaman video.</p>
                <p class="text-gray-600 leading-relaxed mb-6">Kamera tidak menjatuhkan sanksi otomatis. Pengawas yang meninjau daftar kejadian di halaman monitor. Bila kamera HP dimatikan, ujian dijeda sampai kamera menyala lagi.</p>
            </div>

            <div class="reveal bg-[#f0f2f8] rounded-2xl p-4" style="--d: 100ms" role="img" aria-label="Contoh daftar kejadian kamera di halaman monitor admin">
                <div class="flex items-center justify-between mb-3 px-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Contoh log di halaman monitor</p>
                    <span class="flex items-center gap-1.5 text-[11px] text-gray-500"><span class="pulse-dot h-2 w-2 rounded-full bg-secondary"></span>heartbeat 10 dtk</span>
                </div>
                <div id="event-log" class="space-y-2 min-h-[11.5rem]" aria-hidden="true"></div>
            </div>
        </div>
    </div>
</section>

{{-- ═════════ SPESIFIKASI ═════════ --}}
<section id="spesifikasi" class="max-w-[90rem] mx-auto w-full px-6 md:px-12 py-16 md:py-24">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-10 md:gap-16">
        <div class="md:col-span-2 reveal">
            <p class="text-sm font-semibold text-gray-500 tracking-wider uppercase mb-2">Spesifikasi</p>
            <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight leading-tight mb-4">Angka dan batas yang berlaku.</h2>
            <p class="text-gray-600 leading-relaxed">Yang perlu disiapkan: peramban terbaru, dan alamat HTTPS bila ujian memakai kamera, karena peramban hanya mengizinkan akses kamera lewat koneksi aman.</p>
        </div>
        <div class="md:col-span-3 bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden reveal" style="--d: 80ms">
            <dl>
                @foreach([
                    ['Jenis soal', 'Pilihan ganda, koding, esai'],
                    ['Bahasa koding', 'Python, PHP, JavaScript, C / C++, Java, SQL'],
                    ['Impor soal', 'CSV, DOCX, atau ZIP beserta gambar'],
                    ['Jadwal', 'Per kelas: mulai, selesai, durasi, PIN'],
                    ['Percobaan per ujian', '1 sampai 10'],
                    ['Passing grade', '0 sampai 100'],
                    ['Jawaban esai', 'Maksimal 10.000 karakter'],
                    ['Penilaian', 'Otomatis (pilihan ganda), AI atau manual (koding), manual (esai)'],
                    ['Ekspor', 'PDF, CSV, rekap per kelas, API baca-saja'],
                    ['Peramban', 'Chrome, Firefox, atau Edge versi terbaru'],
                    ['Dibangun dengan', 'Laravel 12, MySQL, Tailwind CSS'],
                ] as [$label, $value])
                <div class="spec-row grid grid-cols-1 sm:grid-cols-5 gap-1 sm:gap-4 px-6 md:px-8 py-4 {{ $loop->last ? '' : 'border-b border-gray-100' }}">
                    <dt class="sm:col-span-2 text-sm font-semibold text-gray-500">{{ $label }}</dt>
                    <dd class="sm:col-span-3 text-gray-900">{{ $value }}</dd>
                </div>
                @endforeach
            </dl>
        </div>
    </div>
</section>

{{-- ═════════ PENUTUP ═════════ --}}
<section class="max-w-[90rem] mx-auto w-full px-6 md:px-12 pb-16 md:pb-24">
    <div class="bg-primary text-white rounded-[2rem] p-8 md:p-14 flex flex-col md:flex-row md:items-center md:justify-between gap-8 reveal">
        <div class="max-w-xl">
            <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight leading-tight mb-3">Punya NIM? Masuk dan lihat jadwal ujian Anda.</h2>
            <p class="text-white/70 leading-relaxed">Akun mahasiswa dibuat oleh admin laboratorium. Bila belum bisa masuk, tanyakan ke asisten laboratorium kelas Anda.</p>
        </div>
        <a href="{{ $ctaHref }}" class="inline-flex shrink-0 items-center justify-center gap-2 bg-white hover:bg-[#e8eaf5] text-primary py-3.5 px-7 rounded-xl font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
            {{ $ctaLabel }} <i class="ph ph-arrow-right font-bold" aria-hidden="true"></i>
        </a>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    /* ── Reveal saat masuk viewport ── */
    var reveals = $$('.reveal');
    if (reduce || !('IntersectionObserver' in window)) {
        reveals.forEach(function (el) { el.classList.add('in'); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        reveals.forEach(function (el) { io.observe(el); });
    }

    /* ── Contoh ujian: jawaban dipilih → tersimpan lokal → tersimpan di server ── */
    (function () {
        var cells = $$('#demo-nav .demo-cell');
        var opts = $$('#demo-options .demo-opt');
        var qBox = $('#demo-question');
        var qNum = $('[data-q-num]');
        var qText = $('[data-q-text]');
        var status = $('#demo-status');
        var timerEl = $('#demo-timer');
        if (!cells.length || !opts.length || !status) return;

        var questions = [
            { q: 'Tag HTML mana yang dipakai untuk membuat tautan?', o: ['<a>', '<link>', '<href>', '<url>'], a: 0 },
            { q: 'Properti CSS untuk mengubah warna teks adalah…', o: ['color', 'font-color', 'text-color', 'fg'], a: 0 },
            { q: 'Perintah SQL untuk mengambil data adalah…', o: ['GET', 'FETCH', 'SELECT', 'PULL'], a: 2 },
            { q: 'Method HTTP yang lazim untuk mengirim formulir login?', o: ['GET', 'POST', 'PUT', 'TRACE'], a: 1 },
            { q: 'Hasil dari typeof null di JavaScript?', o: ['null', 'undefined', 'number', 'object'], a: 3 },
        ];

        var STATE = {
            done: ['bg-primary', 'text-white', 'border-primary'],
            pending: ['bg-amber-400', 'text-white', 'border-amber-400'],
            idle: ['bg-white', 'text-gray-500', 'border-gray-200'],
        };
        function setCell(i, name) {
            var c = cells[i];
            Object.keys(STATE).forEach(function (k) { STATE[k].forEach(function (cl) { c.classList.remove(cl); }); });
            STATE[name].forEach(function (cl) { c.classList.add(cl); });
        }
        function setStatus(kind) {
            var icon = $('i', status), label = $('span', status);
            if (kind === 'pending') {
                status.className = 'font-medium text-amber-600 flex items-center gap-1.5';
                icon.className = 'ph-bold ph-cloud-arrow-up';
                label.textContent = 'Tersimpan lokal';
            } else {
                status.className = 'font-medium text-primary flex items-center gap-1.5';
                icon.className = 'ph-bold ph-check';
                label.textContent = 'Tersimpan di server';
            }
        }
        function clearSelection() {
            opts.forEach(function (o) {
                o.classList.remove('border-primary', 'bg-primary/5', 'ring-1', 'ring-primary');
                o.classList.add('border-gray-200');
                var r = $('[data-radio]', o);
                r.classList.remove('border-primary');
                r.classList.add('border-gray-300');
                r.innerHTML = '';
            });
        }
        function select(i) {
            clearSelection();
            var o = opts[i];
            o.classList.remove('border-gray-200');
            o.classList.add('border-primary', 'bg-primary/5', 'ring-1', 'ring-primary');
            var r = $('[data-radio]', o);
            r.classList.remove('border-gray-300');
            r.classList.add('border-primary');
            r.innerHTML = '<span class="w-2 h-2 rounded-full bg-primary"></span>';
        }
        function showQuestion(n, q) {
            qNum.textContent = (n + 1) + '.';
            qText.textContent = q.q;
            opts.forEach(function (o, i) { $('[data-opt]', o).textContent = q.o[i]; });
            clearSelection();
        }

        // Timer hitung mundur dekoratif.
        var left = 59 * 60 + 48;
        function paintTimer() {
            var m = Math.floor(left / 60), s = left % 60;
            timerEl.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }
        if (!reduce) {
            setInterval(function () { left = left > 0 ? left - 1 : 59 * 60 + 59; paintTimer(); }, 1000);
        }

        if (reduce) {
            // Tampilan statis: beberapa soal terjawab, satu menunggu.
            [3, 4].forEach(function (i) { setCell(i, 'done'); });
            setCell(5, 'pending');
            select(questions[0].a);
            return;
        }

        var step = 3;
        function reset() {
            cells.forEach(function (_, i) { setCell(i, i < 3 ? 'done' : 'idle'); cells[i].classList.remove('is-current'); });
            step = 3;
        }
        function tick() {
            var q = questions[step % questions.length];
            qBox.classList.add('out');
            setTimeout(function () {
                showQuestion(step, q);
                qBox.classList.remove('out');
                cells.forEach(function (c) { c.classList.remove('is-current'); });
                cells[step].classList.add('is-current');
            }, 220);
            setTimeout(function () { select(q.a); setCell(step, 'pending'); setStatus('pending'); }, 1100);
            setTimeout(function () { setCell(step, 'done'); setStatus('server'); }, 2300);
            setTimeout(function () {
                step++;
                if (step >= cells.length) { setTimeout(reset, 900); step = cells.length; setTimeout(tick, 1800); return; }
                tick();
            }, 3700);
        }
        setTimeout(tick, 1600);
    })();

    /* ── Garis alur ujian mengikuti scroll ── */
    (function () {
        var tl = $('[data-timeline]');
        if (!tl) return;
        var fill = $('[data-fill]', tl);
        var steps = $$('[data-step]', tl);
        if (reduce) { steps.forEach(function (s) { s.classList.add('active'); }); return; }
        var ticking = false;
        function update() {
            ticking = false;
            var vh = window.innerHeight, line = vh * 0.6;
            var r = tl.getBoundingClientRect();
            var p = Math.max(0, Math.min(1, (line - r.top) / r.height));
            fill.style.transform = 'scaleY(' + p + ')';
            steps.forEach(function (s) {
                s.classList.toggle('active', s.getBoundingClientRect().top < line);
            });
        }
        function onScroll() { if (!ticking) { ticking = true; requestAnimationFrame(update); } }
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        update();
    })();

    /* ── Editor: ganti bahasa ── */
    (function () {
        var body = $('#code-body'), out = $('#code-out');
        var chips = $$('[data-lang-chip]');
        if (!body || !out || !chips.length) return;
        var langs = [
            { c: ['for i in range(1, 4):', '    print("Soal", i)'], o: 'Soal 1<br>Soal 2<br>Soal 3' },
            { c: ['echo implode(", ", range(1, 3));'], o: '1, 2, 3' },
            { c: ['console.log([1, 2, 3].map(n => n * 2));'], o: '[ 2, 4, 6 ]' },
            { c: ['cout << "Halo CBT" << endl;'], o: 'Halo CBT' },
            { c: ['System.out.println("Halo CBT");'], o: 'Halo CBT' },
            { c: ['SELECT nim, nama FROM mahasiswa;'], o: '312010001 | Budi Santoso<br>312010002 | Ani Wijaya' },
        ];
        function esc(t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/ /g, '&nbsp;'); }
        function paint(i) {
            chips.forEach(function (ch, k) {
                var on = k === i;
                ch.classList.toggle('bg-secondary', on);
                ch.classList.toggle('text-white', on);
                ch.classList.toggle('text-gray-400', !on);
            });
            body.innerHTML = langs[i].c.map(esc).join('<br>') + '<span class="caret" aria-hidden="true"></span>';
            out.innerHTML = langs[i].o;
        }
        paint(0);
        if (reduce) return;
        var idx = 0;
        setInterval(function () {
            idx = (idx + 1) % langs.length;
            body.classList.add('out'); out.classList.add('out');
            setTimeout(function () {
                paint(idx);
                body.classList.remove('out'); out.classList.remove('out');
            }, 260);
        }, 3200);
    })();

    /* ── Log kejadian kamera ── */
    (function () {
        var log = $('#event-log');
        if (!log) return;
        var events = [
            { t: 'looking_away', l: 'Menoleh dari layar', s: 'laptop' },
            { t: 'phone_detected', l: 'HP terdeteksi di kamera', s: 'hp' },
            { t: 'no_face', l: 'Wajah tidak terlihat', s: 'laptop' },
            { t: 'book_detected', l: 'Buku terdeteksi', s: 'hp' },
            { t: 'multiple_faces', l: 'Lebih dari satu wajah', s: 'laptop' },
            { t: 'looking_away', l: 'Menoleh dari layar', s: 'laptop' },
        ];
        var clock = 9 * 3600 + 14 * 60 + 2, n = 0;
        function pad(x) { return (x < 10 ? '0' : '') + x; }
        function stamp(sec) { return pad(Math.floor(sec / 3600)) + ':' + pad(Math.floor(sec % 3600 / 60)) + ':' + pad(sec % 60); }
        function add(animate) {
            var e = events[n % events.length];
            clock += 7 + ((n * 5) % 13);
            n++;
            var row = document.createElement('div');
            row.className = 'flex items-start justify-between gap-3 bg-white rounded-xl px-3.5 py-2.5' + (animate ? ' log-in' : '');
            var left = document.createElement('div');
            var title = document.createElement('p');
            title.className = 'text-sm font-semibold text-gray-900';
            title.textContent = e.l;
            var meta = document.createElement('p');
            meta.className = 'text-[11px] text-gray-500 font-mono mt-0.5';
            meta.textContent = e.t + ' · ' + e.s + ' · ' + stamp(clock);
            left.append(title, meta);
            var badge = document.createElement('span');
            badge.className = 'shrink-0 rounded-full bg-amber-50 text-amber-700 text-[11px] font-semibold px-2.5 py-1';
            badge.textContent = 'tercatat';
            row.append(left, badge);
            log.prepend(row);
            while (log.children.length > 4) log.removeChild(log.lastChild);
        }
        for (var i = 0; i < 3; i++) add(false);
        if (reduce) return;
        var started = false, timer = null;
        function start() { if (!started) { started = true; timer = setInterval(function () { add(true); }, 2400); } }
        function stop() { started = false; clearInterval(timer); }
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (es) { es[0].isIntersecting ? start() : stop(); }, { threshold: 0.3 }).observe(log);
        } else { start(); }
    })();
})();
</script>
@endpush
