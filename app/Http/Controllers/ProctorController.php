<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ProctorEvent;
use App\Models\ProctorSignal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProctorController extends Controller
{
    /**
     * Token yang belum terikat ke sesi ujian (mahasiswa masih di halaman
     * persiapan) cukup berumur pendek.
     */
    public const PAIR_TTL_PREPARING_MINUTES = 10;

    /**
     * Begitu terikat ke sesi, pairing harus bertahan selama ujian berjalan.
     * Dulu 5 menit tetap dan hanya diperpanjang oleh heartbeat, sehingga
     * penunjuk (user, exam) → token kedaluwarsa duluan dan ujian "terjeda".
     */
    public const PAIR_TTL_ACTIVE_HOURS = 6;

    /**
     * Tanpa event dari HP selama ini, kamera HP dianggap tidak aktif.
     * Heartbeat dikirim tiap 10 detik, jadi 120 detik toleran terhadap
     * throttling timer di browser HP.
     */
    public const PHONE_STALE_SECONDS = 120;

    public static function pairTtl(bool $bound): \DateTimeInterface
    {
        return $bound
            ? now()->addHours(self::PAIR_TTL_ACTIVE_HOURS)
            : now()->addMinutes(self::PAIR_TTL_PREPARING_MINUTES);
    }

    /**
     * Simpan data pairing dan penunjuk (user, exam) → token dengan TTL yang
     * sama. Penunjuk tidak menimpa token yang lebih baru (mahasiswa scan
     * ulang sementara halaman HP lama masih mengirim event).
     */
    public static function storePairing(string $token, array $data): void
    {
        $ttl = self::pairTtl(! empty($data['session_id']));

        Cache::put("proctor_pair:{$token}", $data, $ttl);

        $pendingKey = "proctor_pair_pending:{$data['user_id']}:{$data['exam_id']}";
        $current = Cache::get($pendingKey);

        if ($current === null || $current === $token) {
            Cache::put($pendingKey, $token, $ttl);
        }
    }

    /**
     * Generate pairing token for phone camera.
     */
    public function createPairingToken(Request $request, Exam $exam)
    {
        $user = auth()->user();

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id)) {
            abort(403);
        }

        $token = Str::random(32);
        $key = "proctor_pair:{$token}";

        $pairData = [
            'session_id' => null,
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'paired' => false,
            'last_event_at' => now()->timestamp,
        ];

        $ttl = self::pairTtl(false);
        Cache::put($key, $pairData, $ttl);
        Cache::put("proctor_pair_pending:{$user->id}:{$exam->id}", $token, $ttl);

        return response()->json([
            'token' => $token,
            'url' => route('proctor.pair', $token),
        ]);
    }

    /**
     * Phone scans QR and opens this page.
     */
    public function showPairPage(string $token)
    {
        $key = "proctor_pair:{$token}";
        $data = Cache::get($key);

        if (! $data) {
            return view('proctor.pair-expired');
        }

        $session = ! empty($data['session_id']) ? ExamSession::with('exam')->find($data['session_id']) : null;
        if ($session && $session->finished_at) {
            return view('proctor.pair-expired');
        }

        $exam = $session?->exam ?? Exam::find($data['exam_id']);
        if (! $exam) {
            return view('proctor.pair-expired');
        }

        return view('proctor.pair', compact('token', 'session', 'exam'));
    }

    /**
     * Verify pairing token for phone.
     */
    public function verifyPairingToken(Request $request, string $token)
    {
        $key = "proctor_pair:{$token}";
        $data = Cache::get($key);

        if (! $data) {
            return response()->json(['valid' => false, 'error' => 'Token expired or invalid'], 404);
        }

        $session = ! empty($data['session_id']) ? ExamSession::find($data['session_id']) : null;
        if ($session && $session->finished_at) {
            return response()->json(['valid' => false, 'error' => 'Session not active'], 404);
        }

        $data['paired'] = true;
        $data['phone_camera_active'] = true;
        $data['last_event_at'] = now()->timestamp;
        self::storePairing($token, $data);

        return response()->json([
            'valid' => true,
            'session_id' => $session?->id,
        ]);
    }

    /**
     * Return whether the paired exam session is still active.
     */
    public function pairingSessionStatus(string $token)
    {
        $data = Cache::get("proctor_pair:{$token}");

        if (! $data || empty($data['session_id'])) {
            return response()->json(['active' => true]);
        }

        $active = ExamSession::whereKey($data['session_id'])
            ->whereNull('finished_at')
            ->exists();

        return response()->json(['active' => $active]);
    }

    /**
     * Return pairing state for the student preparation / exam page.
     *
     * `stale` berarti HP sudah paired dan ujian berjalan, tetapi tidak ada
     * event (heartbeat) dalam PHONE_STALE_SECONDS — misalnya layar HP terkunci.
     * Berbeda dengan `paired=false`, kondisi ini pulih sendiri begitu HP
     * kembali mengirim event.
     */
    public function pairingStatus(Exam $exam)
    {
        $user = auth()->user();

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id)) {
            abort(403);
        }

        $token = Cache::get("proctor_pair_pending:{$user->id}:{$exam->id}");
        $data = $token ? Cache::get("proctor_pair:{$token}") : null;

        $lastEventAt = $data['last_event_at'] ?? null;
        $secondsSinceSeen = $lastEventAt !== null
            ? max(0, now()->timestamp - (int) $lastEventAt)
            : null;

        $stale = ! empty($data['paired'])
            && ! empty($data['session_id'])
            && $secondsSinceSeen !== null
            && $secondsSinceSeen > self::PHONE_STALE_SECONDS;

        return response()->json([
            'paired' => (bool) ($data['paired'] ?? false),
            'token' => $token,
            'camera_active' => (bool) ($data['phone_camera_active'] ?? true),
            'stale' => $stale,
            'seconds_since_seen' => $secondsSinceSeen,
        ]);
    }

    /**
     * Record that the student passed the browser camera check.
     */
    public function markCameraReady(Exam $exam)
    {
        $user = auth()->user();

        if (! $exam->is_active || ! $exam->isClassroomActive($user->classroom_id)) {
            abort(403);
        }

        session()->put("camera_ready:{$exam->id}", true);

        return response()->json(['ready' => true]);
    }

    /**
     * Post a phone event using the short-lived pairing token.
     */
    public function postPairEvent(Request $request, string $token)
    {
        $data = Cache::get("proctor_pair:{$token}");

        if (! $data || empty($data['paired'])) {
            return response()->json(['error' => 'Pairing is not active'], 409);
        }

        $validated = $request->validate([
            'type' => 'required|string|max:40',
            'meta' => 'nullable|array',
        ]);

        // Sesi diambil dari (user, exam) yang sedang berjalan, bukan dari
        // session_id di cache, agar pairing tetap bekerja walau mahasiswa
        // scan ulang di tengah ujian.
        $session = ExamSession::where('user_id', $data['user_id'])
            ->where('exam_id', $data['exam_id'])
            ->whereNull('finished_at')
            ->orderByDesc('attempt_number')
            ->first();

        if (! $session) {
            return empty($data['session_id'])
                ? response()->json(['error' => 'Pairing is not active'], 409)
                : response()->json(['error' => 'Session finished'], 403);
        }

        ProctorEvent::create([
            'exam_session_id' => $session->id,
            'source' => 'phone',
            'type' => $validated['type'],
            'meta' => $validated['meta'] ?? null,
        ]);

        $cameraActive = $validated['type'] !== 'camera_off';
        $data['session_id'] = $session->id;
        $data['phone_camera_active'] = $cameraActive;
        $data['last_event_at'] = now()->timestamp;
        self::storePairing($token, $data);
        $session->update(['phone_seen_at' => $cameraActive ? now() : null]);

        return response()->json(['success' => true]);
    }

    /**
     * Post WebRTC signal (offer, answer, ICE candidate).
     */
    public function postSignal(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:exam_sessions,id',
            'channel' => 'required|in:laptop,phone',
            'viewer_id' => 'required|string|max:64',
            'sender' => 'required|in:student',
            'type' => 'required|string|in:offer,answer,candidate',
            'payload' => 'nullable|string',
        ]);

        $session = ExamSession::whereKey($validated['session_id'])
            ->where('user_id', auth()->id())
            ->whereNull('finished_at')
            ->first();

        if (! $session) {
            return response()->json(['error' => 'Session tidak aktif'], 403);
        }

        ProctorSignal::create($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Poll WebRTC signals.
     */
    public function pollSignals(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:exam_sessions,id',
            'viewer_id' => 'required|string|max:64',
            'since_id' => 'nullable|integer|min:0',
        ]);

        $session = ExamSession::whereKey($validated['session_id'])
            ->where('user_id', auth()->id())
            ->whereNull('finished_at')
            ->exists();

        if (! $session) {
            return response()->json(['error' => 'Session tidak aktif'], 403);
        }

        $query = ProctorSignal::where('viewer_id', $validated['viewer_id'])
            ->where('id', '>', $validated['since_id'] ?? 0)
            ->orderBy('id');

        if (! empty($validated['session_id'])) {
            $query->where('exam_session_id', $validated['session_id']);
        }

        $signals = $query->take(50)->get(['id', 'exam_session_id', 'channel', 'sender', 'type', 'payload', 'created_at']);

        return response()->json(['signals' => $signals]);
    }

    /**
     * Post camera detection events.
     */
    public function postEvent(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:exam_sessions,id',
            'source' => 'required|in:laptop',
            'type' => 'required|string|max:40',
            'meta' => 'nullable|array',
        ]);

        $session = ExamSession::find($validated['session_id']);
        if (! $session
            || $session->user_id !== auth()->id()
            || $session->finished_at
        ) {
            return response()->json(['error' => 'Session finished'], 403);
        }

        ProctorEvent::create([
            'exam_session_id' => $validated['session_id'],
            'source' => $validated['source'],
            'type' => $validated['type'],
            'meta' => $validated['meta'] ?? null,
        ]);

        $session->update(['laptop_seen_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Get events for a session (admin view).
     */
    public function getEvents(Request $request, ExamSession $session)
    {
        $events = $session->proctorEvents()
            ->orderBy('created_at', 'desc')
            ->take(100)
            ->get();

        return response()->json(['events' => $events]);
    }
}
