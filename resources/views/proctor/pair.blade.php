<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kamera HP - {{ $exam->title }}</title>
    @vite(['resources/css/app.css'])
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root { --navy: #001b6e; --navy-dark: #001458; --lavender: #4f50a8; --paper: #f0f2f8; }
        body { font-family: 'Outfit', sans-serif; background: var(--paper); color: #17213c; }
        .phone-shell { box-shadow: 0 24px 70px rgba(0, 27, 110, 0.14); }
        #video-preview { aspect-ratio: 4 / 3; object-fit: cover; }
        .control-button { min-height: 48px; }
        @media (max-width: 420px) {
            .phone-shell { border-radius: 24px; padding: 20px; }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 sm:p-6">
    <main class="phone-shell max-w-md w-full bg-white rounded-[2rem] p-6 sm:p-8">
        <header class="flex items-start justify-between gap-4 mb-7">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl flex items-center justify-center text-white" style="background: var(--navy);">
                    <i class="ph-fill ph-video-camera text-2xl" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color: var(--lavender);">CBT Proctor</p>
                    <h1 class="text-xl font-bold text-gray-900">Kamera HP</h1>
                </div>
            </div>
            <span id="live-badge" class="hidden items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold text-green-700 bg-green-50 border border-green-200">
                <span class="h-2 w-2 rounded-full bg-green-500" aria-hidden="true"></span> Aktif
            </span>
        </header>

        <section class="mb-6">
            <p class="text-sm font-semibold text-gray-900">{{ $exam->title }}</p>
            @if($session)
            <p class="text-xs text-gray-500 mt-1">Sesi #{{ $session->id }} · Kamera kedua terhubung</p>
            @else
            <p class="text-xs text-gray-500 mt-1">Menunggu ujian dimulai di laptop</p>
            @endif
        </section>

        <div id="status-card" class="mb-5 p-4 rounded-2xl bg-indigo-50 border border-indigo-100" role="status" aria-live="polite">
            <p class="text-sm text-indigo-900 text-center">Menginisialisasi kamera...</p>
        </div>

        <div id="placement-guide" class="mb-5 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4">
            <div class="flex items-center justify-between gap-3 mb-2">
                <p class="text-sm font-semibold text-indigo-950">Posisi HP yang disarankan</p>
                <i class="ph ph-info text-lg text-indigo-700" aria-hidden="true"></i>
            </div>
            <img src="{{ asset('images/dual-camera-animation.webp') }}" alt="Contoh posisi HP di samping laptop saat peserta mengerjakan ujian" class="w-full h-auto rounded-xl" loading="eager">
            <p class="text-xs leading-5 text-indigo-900/75 mt-2">Pastikan wajah, tangan, dan area kerja terlihat dari kamera HP.</p>
        </div>

        <div class="relative overflow-hidden rounded-2xl bg-gray-950 mb-4">
            <video id="video-preview" autoplay playsinline muted class="w-full" style="display: none;"></video>
            <span id="camera-label" class="hidden absolute left-3 bottom-3 rounded-lg bg-black/60 px-2.5 py-1 text-xs text-white">Kamera belakang</span>
        </div>

        <div id="controls" class="flex flex-col gap-3" style="display: none;">
            <button type="button" onclick="flipCamera()" aria-label="Balik kamera depan dan belakang" class="control-button flex w-full items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 text-sm font-semibold text-indigo-950 transition-colors hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                <i class="ph ph-arrows-clockwise text-lg" aria-hidden="true"></i>
                <span>Balik Kamera</span>
            </button>
            <button type="button" onclick="toggleCamera()" aria-label="Matikan atau nyalakan kamera" class="control-button flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                <i id="cam-btn-icon" class="ph ph-video-camera-slash text-lg" aria-hidden="true"></i>
                <span id="cam-btn-text">Matikan</span>
            </button>
            <button type="button" onclick="closePage()" aria-label="Tutup halaman kamera" class="control-button flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                <i class="ph ph-x text-lg" aria-hidden="true"></i>
                <span>Tutup halaman</span>
            </button>
        </div>

        <p class="text-xs leading-5 text-gray-500 text-center mt-6">Letakkan HP di samping atau di atas laptop agar area meja dan tangan terlihat.</p>
    </main>

    <script type="module">
        const token = '{{ $token }}';
        const verifyUrl = '{{ route("proctor.pair.verify", $token) }}';
        const statusUrl = '{{ route("proctor.pair.status", $token) }}';
        const eventUrl = '{{ route("proctor.pair.event", $token) }}';
        const csrfToken = '{{ csrf_token() }}';

        const statusCard = document.getElementById('status-card');
        const videoEl = document.getElementById('video-preview');
        const controlsEl = document.getElementById('controls');

        let stream = null;
        let detector = null;
        let detecting = false;
        let lastEvents = {};
        let pendingEvents = {};
        let retryAt = {};
        let heartbeatTimer = null;
        let pairStatusTimer = null;
        let viewerId = 'phone_' + Math.random().toString(36).slice(2, 11);
        let cameraFacingMode = 'environment';
        let cameraStarting = false;

        async function init() {
            try {
                const res = await fetch(verifyUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken } });
                const data = await res.json();
                if (!data.valid) {
                    showStatus('Token tidak valid atau kedaluwarsa', 'red');
                    return;
                }
                pairStatusTimer = window.setInterval(checkPairStatus, 2000);
            } catch (e) {
                showStatus('Gagal verifikasi token', 'red');
                return;
            }

            if (!window.isSecureContext || !navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                showStatus('Kamera HP membutuhkan HTTPS. Buka QR dari alamat https:// atau gunakan localhost di perangkat yang sama.', 'red');
                return;
            }

            try {
                await startCamera();
                if (!stream) return;
                
                if (!await initDetector()) {
                    throw new Error('Object detector gagal dimuat');
                }
                startDetection();
            } catch (e) {
                stopCamera();
                showStatus('Gagal mengakses kamera: ' + e.message, 'red');
            }
        }

        async function checkPairStatus() {
            try {
                const response = await fetch(statusUrl, { cache: 'no-store' });
                const data = await response.json();
                if (!data.active) {
                    stopCamera();
                    showStatus('Ujian selesai. Kamera dimatikan.', 'gray');
                    if (pairStatusTimer) {
                        clearInterval(pairStatusTimer);
                        pairStatusTimer = null;
                    }
                }
            } catch (e) {
                console.warn('Pair status check failed:', e);
            }
        }

        async function startCamera() {
            if (cameraStarting) return;
            cameraStarting = true;
            stopCamera();
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: cameraFacingMode } }, audio: false });
                videoEl.srcObject = stream;
                videoEl.style.display = 'block';
                controlsEl.style.display = 'block';
                document.getElementById('live-badge').classList.remove('hidden');
                document.getElementById('live-badge').classList.add('inline-flex');
                document.getElementById('camera-label').textContent = cameraFacingMode === 'environment' ? 'Kamera belakang' : 'Kamera depan';
                document.getElementById('camera-label').classList.remove('hidden');
                showStatus('Kamera aktif', 'green');
                notifyCameraState(true);
                if (detector) startDetection();
            } catch (e) {
                stream = null;
                throw e;
            } finally {
                cameraStarting = false;
            }
        }

        async function initDetector() {
            try {
                const module = await import('{{ Vite::asset('resources/js/proctor-phone.js') }}');
                detector = await module.createPhoneDetector();
            } catch (e) {
                console.warn('Failed to load object detector:', e.message, e);
                return false;
            }
            return true;
        }

        function startDetection() {
            if (!detector) return;
            detecting = true;
            const loop = async () => {
                if (!detecting) return;
                if (videoEl.readyState >= 2) {
                    const result = detector.detectForVideo(videoEl, Date.now());
                    processDetections(result.detections);
                }
                requestAnimationFrame(loop);
            };
            loop();
            heartbeatTimer = window.setInterval(() => sendEvent('heartbeat', null), 10000);
        }

        function processDetections(detections) {
            const phone = detections.find(d => d.categories[0].categoryName === 'cell phone');
            const book = detections.find(d => d.categories[0].categoryName === 'book');

            sendEventIfChanged('phone_detected', !!phone, phone ? { score: phone.categories[0].score } : null);
            sendEventIfChanged('book_detected', !!book, book ? { score: book.categories[0].score } : null);
        }

        function sendEventIfChanged(type, detected, meta) {
            if (lastEvents[type] === detected && !retryAt[type]) return;
            if (retryAt[type] && retryAt[type] > Date.now()) return;
            if (pendingEvents[type]) return;
            pendingEvents[type] = true;

            sendEvent(type, meta, detected);
        }

        function sendEvent(type, meta = null, detected = null) {
            fetch(eventUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ type, meta }),
            }).then(response => {
                if (response.status === 403) {
                    stopCamera();
                    showStatus('Ujian selesai. Kamera dimatikan.', 'gray');
                }
                if (!response.ok) throw new Error('Event request failed');
                if (detected !== null) {
                    lastEvents[type] = detected;
                    delete retryAt[type];
                }
            }).catch(() => {
                if (detected !== null) retryAt[type] = Date.now() + 2000;
            }).finally(() => {
                delete pendingEvents[type];
            });
        }

        function stopCamera() {
            detecting = false;
            if (heartbeatTimer) {
                clearInterval(heartbeatTimer);
                heartbeatTimer = null;
            }
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }
            videoEl.srcObject = null;
            videoEl.style.display = 'none';
            document.getElementById('live-badge').classList.add('hidden');
            document.getElementById('live-badge').classList.remove('inline-flex');
            document.getElementById('camera-label').classList.add('hidden');
        }

        function notifyCameraState(active) {
            fetch(eventUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ type: active ? 'camera_on' : 'camera_off', meta: null }),
                keepalive: !active,
            }).catch(() => {});
        }

        async function flipCamera() {
            if (!stream || cameraStarting) return;
            cameraFacingMode = cameraFacingMode === 'environment' ? 'user' : 'environment';
            try {
                await startCamera();
                showStatus(cameraFacingMode === 'environment' ? 'Kamera belakang aktif' : 'Kamera depan aktif', 'green');
            } catch (e) {
                showStatus('Kamera tidak dapat dibalik: ' + e.message, 'red');
            }
        }

        function toggleCamera() {
            if (stream) {
                notifyCameraState(false);
                stopCamera();
                showStatus('Kamera dimatikan', 'gray');
                document.getElementById('cam-btn-text').textContent = 'Nyalakan Kamera';
                document.getElementById('cam-btn-icon').className = 'ph ph-video-camera mr-1';
            } else {
                startCamera().then(() => {
                    document.getElementById('cam-btn-text').textContent = 'Matikan';
                    document.getElementById('cam-btn-icon').className = 'ph ph-video-camera-slash mr-1';
                }).catch(e => showStatus('Gagal mengakses kamera: ' + e.message, 'red'));
            }
        }

        function closePage() {
            notifyCameraState(false);
            stopCamera();
            window.close();
        }

        function showStatus(msg, color) {
            const colors = {
                green: 'bg-green-50 border-green-200 text-green-700',
                red: 'bg-red-50 border-red-200 text-red-700',
                gray: 'bg-gray-50 border-gray-200 text-gray-700',
                blue: 'bg-blue-50 border-blue-200 text-blue-700',
            };
            statusCard.className = 'mb-6 p-4 rounded-xl border ' + (colors[color] || colors.blue);
            statusCard.innerHTML = '<p class="text-sm text-center">' + msg + '</p>';
        }

        window.flipCamera = flipCamera;
        window.toggleCamera = toggleCamera;
        window.closePage = closePage;
        init();
    </script>
</body>
</html>
