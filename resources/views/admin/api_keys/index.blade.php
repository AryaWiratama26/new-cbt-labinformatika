@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">API Keys</h2>
            <p class="text-gray-500">Kunci akses read-only untuk web lain (endpoint nilai di <span class="font-mono text-sm">/api/v1/*</span>).</p>
        </div>
    </div>

    {{-- Plaintext key: shown exactly once, right after generation.
        Amber treatment reason: a one-time secret must stand out from routine
        UI so it is not missed. Real state, real action (copy button). --}}
    @if(session('plain_key'))
        <div class="mb-6 p-5 rounded-2xl bg-amber-50 border border-amber-200">
            <p class="font-bold text-amber-800 mb-1 flex items-center gap-2">
                <i class="ph ph-warning-circle text-lg"></i> Salin sekarang, key ini tidak akan ditampilkan lagi
            </p>
            <div class="flex flex-col sm:flex-row gap-2">
                <p id="plain-api-key" class="flex-1 font-mono text-sm text-gray-900 bg-white border border-amber-200 rounded-xl px-4 py-3 break-all select-all">{{ session('plain_key') }}</p>
                <button type="button" id="copy-api-key" class="shrink-0 inline-flex items-center justify-center gap-2 px-5 min-h-[44px] text-sm font-semibold bg-amber-600 hover:bg-amber-700 text-white rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2">
                    <i class="ph ph-copy text-lg"></i> <span>Salin</span>
                </button>
            </div>
            <p class="text-xs text-amber-700 mt-2" aria-live="polite"><span id="copy-api-key-hint">Tempel key ini di web lain sebagai header <span class="font-mono">X-API-Key</span>.</span></p>
        </div>
        <script>
        document.getElementById('copy-api-key')?.addEventListener('click', async function () {
            var keyText = document.getElementById('plain-api-key')?.textContent.trim() || '';
            var hint = document.getElementById('copy-api-key-hint');
            var label = this.querySelector('span');
            var done = function (ok) {
                if (ok) {
                    if (label) label.textContent = 'Disalin';
                    if (hint) hint.textContent = 'Key tersalin, tempel di web lain sebagai header X-API-Key.';
                    setTimeout(function () {
                        if (label) label.textContent = 'Salin';
                    }, 2000);
                } else if (hint) {
                    hint.textContent = 'Gagal menyalin otomatis, blok key di atas lalu salin manual (Ctrl+C).';
                }
            };
            if (navigator.clipboard && window.isSecureContext) {
                try { await navigator.clipboard.writeText(keyText); done(true); return; }
                catch (e) { /* fall through to legacy path */ }
            }
            try {
                var ta = document.createElement('textarea');
                ta.value = keyText;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                done(document.execCommand('copy'));
                document.body.removeChild(ta);
            } catch (e) { done(false); }
        });
        </script>
    @endif

    <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden mb-8">
        <form method="POST" action="{{ route('admin.api_keys.store') }}" class="p-5 flex flex-col md:flex-row gap-3 md:items-end">
            @csrf
            <div class="flex-1">
                <label for="api-key-name" class="block text-sm font-semibold text-gray-900 mb-1.5">Nama aplikasi klien</label>
                <input type="text" id="api-key-name" name="name" required maxlength="100" autocomplete="off" placeholder="Contoh: Portal Akademik, Aplikasi Absensi"
                    class="w-full px-4 py-2.5 text-sm rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 text-sm font-medium bg-primary text-white rounded-xl hover:bg-primary-hover transition-colors">
                <i class="ph ph-key"></i> Buat Key Baru
            </button>
        </form>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Nama</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Prefix</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Terakhir Dipakai</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Dibuat</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm">Status</th>
                        <th class="py-4 px-6 font-semibold text-gray-600 text-sm text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($keys as $key)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-4 px-6 font-medium text-gray-900">{{ $key->name }}</td>
                        <td class="py-4 px-6 font-mono text-sm text-gray-600">{{ $key->prefix }}…</td>
                        <td class="py-4 px-6 text-sm text-gray-600">{{ $key->last_used_at?->format('d M Y, H:i') ?? 'Belum pernah' }}</td>
                        <td class="py-4 px-6 text-sm text-gray-600">{{ $key->created_at->format('d M Y, H:i') }}</td>
                        <td class="py-4 px-6">
                            @if($key->revoked_at)
                                <span class="px-3 py-1 bg-red-50 text-red-700 text-xs font-semibold rounded-full border border-red-200 whitespace-nowrap">Dicabut</span>
                            @elseif($key->expires_at && $key->expires_at->isPast())
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full border border-gray-200 whitespace-nowrap">Kedaluwarsa</span>
                            @else
                                <span class="px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full border border-green-200 whitespace-nowrap">Aktif</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-right">
                            @if(!$key->revoked_at)
                                <form action="{{ route('admin.api_keys.destroy', $key) }}" method="POST" class="inline-block" data-confirm="Web klien yang memakai key ini langsung kehilangan akses." data-confirm-title="Cabut key {{ $key->name }}?" data-confirm-text="Ya, cabut">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Cabut">
                                        <i class="ph ph-prohibit text-lg"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-gray-500">Belum ada API key. Buat satu untuk web lain di form atas.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($keys->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 text-sm text-gray-500">
                Menampilkan {{ $keys->firstItem() }}–{{ $keys->lastItem() }} dari {{ $keys->total() }} key
            </div>
        @endif
    </div>
</div>
@endsection
