@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('student.dashboard') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">
                {{ $hasUnfinished ? 'Lanjutkan Ujian' : ($canRemedial ? 'Remedial' : 'Persiapan Ujian') }}
            </h2>
            <p class="text-gray-500">Baca detail dan peraturan sebelum memulai.</p>
        </div>
    </div>

    @if($canRemedial && $exam->max_attempts > 1)
    <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-start gap-3">
        <i class="ph ph-warning text-xl flex-shrink-0"></i>
        <div class="text-sm">
            <strong>Remedial:</strong> Nilai Anda sebelumnya (<strong>{{ $lastSession->score }}</strong>) di bawah nilai minimal <strong>{{ $exam->passing_grade }}</strong>. Anda masih memiliki kesempatan remedial (Percobaan {{ $lastSession->attempt_number + 1 }} dari {{ $exam->max_attempts }}).
        </div>
    </div>
    @endif

    @if($hasUnfinished)
    <div class="mb-6 p-4 rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-800 flex items-start gap-3">
        <i class="ph ph-clock-counter-clockwise text-xl flex-shrink-0"></i>
        <div class="text-sm">
            <strong>Ujian belum selesai!</strong> Anda memiliki sesi ujian yang masih berjalan. Klik tombol di bawah untuk melanjutkan.
        </div>
    </div>
    @endif

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl flex-shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl flex-shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 text-center mb-6">
        <div class="inline-flex items-center justify-center h-20 w-20 rounded-2xl bg-primary/10 text-primary mb-6">
            <i class="ph ph-exam text-4xl"></i>
        </div>
        
        <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $exam->title }}</h3>
        <p class="text-gray-600 mb-8">{{ $exam->course->name ?? '-' }}</p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-left mb-8 border-y border-gray-100 py-6">
            <div>
                <span class="block text-xs text-gray-500 mb-1">Tanggal</span>
                <span class="font-medium text-gray-900">{{ $exam->start_time->format('d M Y') }}</span>
            </div>
            <div>
                <span class="block text-xs text-gray-500 mb-1">Waktu</span>
                <span class="font-medium text-gray-900">{{ $exam->start_time->format('H:i') }} WIB</span>
            </div>
            <div>
                <span class="block text-xs text-gray-500 mb-1">Durasi</span>
                <span class="font-medium text-gray-900">{{ $exam->duration_minutes }} Menit</span>
            </div>
            <div>
                <span class="block text-xs text-gray-500 mb-1">Total Soal</span>
                <span class="font-medium text-gray-900">
                    {{ $exam->getQuestionsCount() }} Butir
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-left mb-8">
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <span class="block text-xs text-gray-500 mb-1">Nilai Minimal</span>
                <span class="font-bold text-lg text-gray-900">{{ $exam->passing_grade }}</span>
            </div>
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <span class="block text-xs text-gray-500 mb-1">Maksimal Percobaan</span>
                <span class="font-bold text-lg text-gray-900">{{ $exam->max_attempts }}x</span>
            </div>
            @if($exam->max_tab_switches)
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <span class="block text-xs text-gray-500 mb-1">Deteksi Tab</span>
                <span class="font-bold text-lg text-gray-900">Aktif ({{ $exam->max_tab_switches }}x)</span>
            </div>
            @endif
            @if($exam->require_fullscreen)
            <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                <span class="block text-xs text-gray-500 mb-1">Layar Penuh</span>
                <span class="font-bold text-lg text-gray-900">Wajib</span>
            </div>
            @endif
        </div>

        @if($exam->description)
            <div class="text-left bg-gray-50 p-6 rounded-2xl border border-gray-100 mb-8">
                <h4 class="font-bold text-gray-900 mb-2 flex items-center gap-2">
                    <i class="ph-fill ph-info text-primary"></i> Instruksi / Peraturan
                </h4>
                <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $exam->description }}</p>
            </div>
        @endif

        @if($hasUnfinished || $canRemedial || $lastSession === null)

        @if($needsPin)
        @php $pinError = session('pin_error'); @endphp
        <div class="mb-6 p-5 rounded-2xl {{ $pinError ? 'bg-red-50 border border-red-300' : 'bg-amber-50 border border-amber-200' }}">
            <div class="flex items-start gap-3">
                <div class="h-10 w-10 rounded-xl flex items-center justify-center flex-shrink-0 {{ $pinError ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                    <i class="{{ $pinError ? 'ph ph-warning-circle' : 'ph ph-lock' }} text-xl"></i>
                </div>
                <div class="text-left flex-1">
                    <strong class="{{ $pinError ? 'text-red-900' : 'text-amber-900' }}">Ujian Dilindungi PIN</strong>
                    <p class="text-sm mt-0.5 {{ $pinError ? 'text-red-700' : 'text-amber-700' }}">
                        @if($pinError)
                            PIN yang Anda masukkan salah. Silakan coba lagi atau hubungi dosen pengawas.
                        @else
                            Masukkan PIN yang dibagikan oleh dosen pengawas untuk memulai ujian.
                        @endif
                    </p>
                </div>
            </div>
            <form action="{{ route('student.exams.verify_pin', $exam) }}" method="POST" class="mt-3">
                @csrf
                <div class="flex gap-3">
                    <input type="text" name="pin" maxlength="10" placeholder="Masukkan PIN" autocomplete="off"
                        class="flex-1 px-4 py-3 border rounded-xl bg-white text-center text-lg font-bold tracking-widest transition-colors focus:ring-2 focus:outline-none
                        {{ $pinError ? 'border-red-400 focus:ring-red-300 focus:border-red-500' : 'border-amber-300 focus:ring-amber-300 focus:border-amber-400' }}">
                    <button type="submit" class="px-6 py-3 rounded-xl font-semibold transition-colors flex items-center gap-2 text-white {{ $pinError ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700' }}">
                        <i class="ph ph-key"></i> Verifikasi
                    </button>
                </div>
            </form>
        </div>
        @endif

        @if($exam->camera_proctoring)
        <div id="camera-gate" class="mb-6 p-5 rounded-2xl bg-green-50 border border-green-200">
            <div class="flex items-start gap-3">
                <div class="h-10 w-10 rounded-xl bg-green-100 flex items-center justify-center text-green-700 flex-shrink-0">
                    <i class="ph ph-video-camera text-xl"></i>
                </div>
                <div class="text-left">
                    <strong class="text-green-900">Pemantauan Kamera</strong>
                    <p class="text-sm text-green-700 mt-0.5">Ujian ini menggunakan pemantauan kamera. Klik tombol di bawah untuk memberikan izin kamera.</p>
                </div>
            </div>
            <button type="button" id="camera-consent-btn" onclick="requestCameraAccess()" class="mt-3 w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl font-semibold transition-colors flex items-center justify-center gap-2">
                <i class="ph-fill ph-video-camera text-lg"></i> Izinkan Kamera
            </button>
            <div id="camera-active-indicator" class="hidden mt-3 p-3 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-medium flex items-center gap-2">
                <i class="ph-fill ph-check-circle text-lg"></i> Kamera siap.
            </div>
            @if($exam->camera_phone)
            <div id="phone-pairing-panel" class="hidden mt-4 pt-4 border-t border-green-200">
                <p class="text-sm font-semibold text-gray-800">Hubungkan Kamera HP</p>
                <p class="text-xs text-gray-500 mt-1">Scan QR ini dengan HP. Tombol mulai aktif setelah HP berhasil terhubung.</p>
                <div id="prep-qr-code" class="flex justify-center my-4 min-h-[220px] items-center"></div>
                <div id="phone-pairing-status" class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-sm flex items-center gap-2">
                    <i class="ph ph-spinner animate-spin"></i> Menyiapkan QR...
                </div>
            </div>
            @endif
        </div>
        @endif

        @if($exam->require_fullscreen)
        <div id="fullscreen-gate" class="mb-6 p-5 rounded-2xl bg-blue-50 border border-blue-200">
            <div class="flex items-start gap-3">
                <div class="h-10 w-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-700 flex-shrink-0">
                    <i class="ph ph-arrows-out text-xl"></i>
                </div>
                <div class="text-left">
                    <strong class="text-blue-900">Wajib Layar Penuh</strong>
                    <p class="text-sm text-blue-700 mt-0.5">Ujian ini mengharuskan Anda berada dalam mode layar penuh. Klik tombol di bawah, lalu mulai ujian.</p>
                </div>
            </div>
            <button type="button" id="fs-enter-btn" onclick="enterFullscreen()" class="mt-3 w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-semibold transition-colors flex items-center justify-center gap-2">
                <i class="ph-fill ph-arrows-out text-lg"></i> Masuk Layar Penuh
            </button>
            <div id="fs-active-indicator" class="hidden mt-3 p-3 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-medium flex items-center gap-2">
                <i class="ph-fill ph-check-circle text-lg"></i> Mode layar penuh aktif. Silakan mulai ujian.
            </div>
        </div>
        @endif
        <form action="{{ route('student.exams.start', $exam) }}" method="POST">
            @csrf
            <button type="submit" id="start-exam-btn" class="w-full bg-primary hover:bg-primary-hover text-white py-4 rounded-xl font-bold transition-colors text-lg flex justify-center items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed" {{ $exam->require_fullscreen || $exam->camera_proctoring || $needsPin ? 'disabled' : '' }}>
                <i class="ph-fill ph-play-circle"></i> 
                @if($hasUnfinished)
                    Lanjutkan Pengerjaan
                @elseif($canRemedial)
                    Mulai Remedial (Percobaan {{ $lastSession->attempt_number + 1 }})
                @else
                    Mulai Kerjakan Sekarang
                @endif
            </button>
            <p class="text-xs text-gray-500 mt-3">Waktu akan mulai berjalan saat Anda mengklik tombol di atas.</p>
        </form>
        @elseif($lastSession && $lastSession->finished_at && !$canRemedial)
        <div class="w-full bg-gray-100 text-gray-500 py-4 rounded-xl font-medium text-center border border-gray-200 cursor-not-allowed">
            <i class="ph ph-check-circle text-xl inline-block mb-1"></i>
            <p>Anda sudah menyelesaikan ujian ini.</p>
            @if($lastSession->score >= $exam->passing_grade)
                <p class="text-sm text-green-600 mt-1">Nilai: {{ $lastSession->score }} (Lulus)</p>
            @else
                <p class="text-sm text-red-500 mt-1">Nilai: {{ $lastSession->score }} (Batas percobaan habis)</p>
            @endif
        </div>
        @endif
    </div>
</div>

<script>
@if($exam->camera_proctoring)
let cameraGranted = false;
let cameraStream = null;
@if($exam->camera_phone)
let phonePaired = false;
let pairingPoll = null;
@endif

async function requestCameraAccess() {
    const btn = document.getElementById('camera-consent-btn');
    const indicator = document.getElementById('camera-active-indicator');
    const startBtn = document.getElementById('start-exam-btn');

    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Meminta izin...';

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        const readyResponse = await fetch('{{ route("proctor.camera_ready", $exam) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        });
        if (!readyResponse.ok) throw new Error('Status kamera gagal disimpan');
        cameraGranted = true;
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;

        btn.classList.add('hidden');
        indicator.classList.remove('hidden');
        @if($exam->camera_phone)
        preparePhonePairing();
        @endif
        checkAllGates();
    } catch (e) {
        console.error('Camera permission denied:', e);
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        alert('Izin kamera ditolak. Anda harus memberikan izin kamera untuk mengikuti ujian ini.');
        btn.disabled = false;
        btn.innerHTML = '<i class="ph-fill ph-video-camera"></i> Izinkan Kamera';
    }
}

@endif

@if($exam->camera_phone)
async function preparePhonePairing() {
    const panel = document.getElementById('phone-pairing-panel');
    const status = document.getElementById('phone-pairing-status');
    const qrContainer = document.getElementById('prep-qr-code');
    if (!panel || !status || !qrContainer) return;

    panel.classList.remove('hidden');
    try {
        const response = await fetch('{{ route("proctor.pair_token", $exam) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        });
        if (!response.ok) throw new Error('Token pairing gagal dibuat');
        const data = await response.json();
        const QRCode = await window.qrCodeReady;
        const canvas = document.createElement('canvas');
        await QRCode.toCanvas(canvas, data.url, { width: 220, margin: 1 });
        qrContainer.replaceChildren(canvas);
        status.innerHTML = '<i class="ph ph-device-mobile"></i> Menunggu HP melakukan scan...';
        checkPairingStatus();
    } catch (error) {
        console.error(error);
        status.className = 'p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2';
        status.innerHTML = '<i class="ph ph-warning-circle"></i> QR gagal dibuat. Muat ulang halaman untuk mencoba lagi.';
    }
}

async function checkPairingStatus() {
    try {
        const response = await fetch('{{ route("proctor.pair_status", $exam) }}');
        const data = await response.json();
        if (data.paired) {
            phonePaired = true;
            const status = document.getElementById('phone-pairing-status');
            status.className = 'p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm flex items-center gap-2';
            status.innerHTML = '<i class="ph-fill ph-check-circle"></i> Kamera HP terhubung.';
            if (pairingPoll) clearTimeout(pairingPoll);
            checkAllGates();
            return;
        }
    } catch (error) {
        console.warn('Pairing status check failed', error);
    }
    pairingPoll = setTimeout(checkPairingStatus, 2000);
}
@endif

@if($exam->require_fullscreen)
function fsActive() {
    return document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
}

function enterFullscreen() {
    var el = document.documentElement;
    if (el.requestFullscreen) {
        el.requestFullscreen();
    } else if (el.webkitRequestFullscreen) {
        el.webkitRequestFullscreen();
    } else if (el.msRequestFullscreen) {
        el.msRequestFullscreen();
    }
}

function handleFullscreenChange() {
    var isFs = fsActive();
    var gate = document.getElementById('fullscreen-gate');
    var indicator = document.getElementById('fs-active-indicator');
    var enterBtn = document.getElementById('fs-enter-btn');
    if (!gate) return;
    if (isFs) {
        gate.classList.add('hidden');
        if (indicator) indicator.classList.remove('hidden');
        if (enterBtn) enterBtn.classList.add('hidden');
    } else {
        gate.classList.remove('hidden');
        if (indicator) indicator.classList.add('hidden');
        if (enterBtn) enterBtn.classList.remove('hidden');
    }
    checkAllGates();
}

document.addEventListener('fullscreenchange', handleFullscreenChange);
document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
document.addEventListener('msfullscreenchange', handleFullscreenChange);
@endif

function checkAllGates() {
    const startBtn = document.getElementById('start-exam-btn');
    if (!startBtn) return;

    let canStart = true;

    @if($exam->camera_proctoring)
    if (!cameraGranted) canStart = false;
    @endif

    @if($exam->camera_phone)
    if (!phonePaired) canStart = false;
    @endif

    @if($exam->require_fullscreen)
    if (!fsActive()) canStart = false;
    @endif

    @if($needsPin)
    canStart = false;
    @endif

    startBtn.disabled = !canStart;
}

@if($exam->require_fullscreen)
if (fsActive()) handleFullscreenChange();
@endif
</script>
@endsection
