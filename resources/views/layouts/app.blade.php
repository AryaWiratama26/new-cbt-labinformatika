<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'CBT Praktikum') }}</title>
    <!-- BUG #16 fix: CSRF meta tag untuk AJAX POST support -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- KaTeX for math rendering -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
    
    <!-- Prism.js for code syntax highlighting -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/line-numbers/prism-line-numbers.min.css">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Admin sidebar rail + drawer. Presentational only; no function changes. */
        #admin-sidebar { transition: width .25s ease, transform .25s ease; }
        #app-main { transition: margin .25s ease; }
        @media (min-width: 768px) {
            html.sidebar-collapsed #admin-sidebar { width: 5rem; }
            html.sidebar-collapsed #app-main { margin-left: 5rem; }
            html.sidebar-collapsed .nav-label,
            html.sidebar-collapsed .nav-caret,
            html.sidebar-collapsed .sidebar-full,
            html.sidebar-collapsed #sidebar-user-info { display: none; }
            html.sidebar-collapsed .nav-link,
            html.sidebar-collapsed .nav-toggle { justify-content: center; padding-left: 0; padding-right: 0; }
            html.sidebar-collapsed .nav-group .nav-menu { display: none; }
            html.sidebar-collapsed #sidebar-collapse i { transform: rotate(180deg); }
            html.sidebar-collapsed #sidebar-user-row { flex-direction: column; align-items: center; gap: 0.5rem; }
            #sidebar-collapse i { transition: transform .25s ease; }
        }
        .nav-menu { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .25s ease; }
        .nav-menu > div { overflow: hidden; }
        .nav-group.open .nav-menu { grid-template-rows: 1fr; }
        .nav-group.open .nav-caret { transform: rotate(180deg); }
        .nav-caret { transition: transform .2s ease; }
    </style>
    <script>
        try { if (localStorage.getItem('cbt-sidebar-collapsed') === '1') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}
    </script>
</head>
<body class="antialiased min-h-screen flex flex-col selection:bg-primary selection:text-white">

    <!-- Navigation (if logged in) -->
    @auth
    @if(Auth::user()->role === 'admin')
    @php
        $navDashboard = request()->routeIs('admin.dashboard');
        $navAnalytics = request()->routeIs('admin.analytics');
        $navMaster = request()->routeIs('admin.courses.*', 'admin.classrooms*');
        $navExams = request()->routeIs('admin.exams.*');
        $navStudents = request()->routeIs('admin.students.*');
        $navApiKeys = request()->routeIs('admin.api_keys.*');
    @endphp
    <!-- Mobile topbar (admin only) -->
    <div class="md:hidden bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between sticky top-0 z-40">
        <button type="button" id="sidebar-open" class="p-2 -ml-2 rounded-lg text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="admin-sidebar">
            <i class="ph ph-list text-2xl"></i>
        </button>
        <div class="flex items-center gap-2">
            <img src="{{ asset('images/logo-upb.png') }}" alt="Logo Universitas" class="h-7 object-contain" onerror="this.src='https://placehold.co/100x100?text=Logo'">
            <span class="font-bold text-gray-900 text-sm">CBT Praktikum</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="p-2 -mr-2 rounded-lg text-gray-500 hover:text-red-500 transition-colors" title="Logout" aria-label="Keluar">
                <i class="ph ph-sign-out text-2xl"></i>
            </button>
        </form>
    </div>
    <!-- Mobile drawer overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"></div>
    <!-- Sidebar (admin only) -->
    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full md:translate-x-0 bg-white border-r border-gray-200 flex flex-col" aria-label="Navigasi admin">
        <div class="flex items-center gap-3 px-5 pt-5 pb-4">
            <img src="{{ asset('images/logo-upb.png') }}" alt="Logo Universitas" class="h-9 object-contain shrink-0" onerror="this.src='https://placehold.co/100x100?text=Logo'">
            <div class="sidebar-full min-w-0">
                <h1 class="font-bold text-gray-900 leading-tight truncate">CBT Praktikum</h1>
                <p class="text-xs text-gray-500 truncate">Universitas Pelita Bangsa</p>
            </div>
            <button type="button" id="sidebar-close" class="md:hidden ml-auto p-2 -mr-2 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Tutup menu navigasi">
                <i class="ph ph-x text-2xl"></i>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto px-4 pb-4 space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $navDashboard ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" title="Dashboard" @if($navDashboard) aria-current="page" @endif>
                <i class="ph ph-squares-four text-xl shrink-0"></i><span class="nav-label">Dashboard</span>
            </a>
            <a href="{{ route('admin.analytics') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $navAnalytics ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" title="Analitik" @if($navAnalytics) aria-current="page" @endif>
                <i class="ph ph-chart-bar text-xl shrink-0"></i><span class="nav-label">Analitik</span>
            </a>
            <div class="nav-group {{ $navMaster ? 'open' : '' }}" data-group>
                <button type="button" data-group-toggle aria-expanded="{{ $navMaster ? 'true' : 'false' }}" class="nav-toggle w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $navMaster ? 'bg-[#e8eaf5] text-primary' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" title="Master Data">
                    <i class="ph ph-stack text-xl shrink-0"></i><span class="nav-label flex-1 text-left">Master Data</span><i class="ph ph-caret-down nav-caret shrink-0"></i>
                </button>
                <div class="nav-menu"><div>
                    <div class="ml-5 mt-1 mb-1 space-y-1 border-l border-gray-200 pl-3">
                        <a href="{{ route('admin.courses.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.courses.*') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" @if(request()->routeIs('admin.courses.*')) aria-current="page" @endif>Mata Kuliah</a>
                        <a href="{{ route('admin.classrooms') }}" class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.classrooms*') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" @if(request()->routeIs('admin.classrooms*')) aria-current="page" @endif>Kelas</a>
                    </div>
                </div></div>
            </div>
            <div class="nav-group {{ $navExams ? 'open' : '' }}" data-group>
                <button type="button" data-group-toggle aria-expanded="{{ $navExams ? 'true' : 'false' }}" class="nav-toggle w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $navExams ? 'bg-[#e8eaf5] text-primary' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" title="Ujian">
                    <i class="ph ph-exam text-xl shrink-0"></i><span class="nav-label flex-1 text-left">Ujian</span><i class="ph ph-caret-down nav-caret shrink-0"></i>
                </button>
                <div class="nav-menu"><div>
                    <div class="ml-5 mt-1 mb-1 space-y-1 border-l border-gray-200 pl-3">
                        <a href="{{ route('admin.exams.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.exams.index') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" @if(request()->routeIs('admin.exams.index')) aria-current="page" @endif>Daftar Ujian</a>
                        <a href="{{ route('admin.exams.create') }}" class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.exams.create') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" @if(request()->routeIs('admin.exams.create')) aria-current="page" @endif>Buat Ujian Baru</a>
                    </div>
                </div></div>
            </div>
            <div class="nav-group {{ $navStudents ? 'open' : '' }}" data-group>
                <button type="button" data-group-toggle aria-expanded="{{ $navStudents ? 'true' : 'false' }}" class="nav-toggle w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $navStudents ? 'bg-[#e8eaf5] text-primary' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" title="Mahasiswa">
                    <i class="ph ph-users text-xl shrink-0"></i><span class="nav-label flex-1 text-left">Mahasiswa</span><i class="ph ph-caret-down nav-caret shrink-0"></i>
                </button>
                <div class="nav-menu"><div>
                    <div class="ml-5 mt-1 mb-1 space-y-1 border-l border-gray-200 pl-3">
                        <a href="{{ route('admin.students.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.students.index') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" @if(request()->routeIs('admin.students.index')) aria-current="page" @endif>Daftar Mahasiswa</a>
                        <a href="{{ route('admin.students.create') }}" class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.students.create') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" @if(request()->routeIs('admin.students.create')) aria-current="page" @endif>Tambah Mahasiswa</a>
                        <a href="{{ route('admin.students.move.form') }}" class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.students.move*') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" @if(request()->routeIs('admin.students.move*')) aria-current="page" @endif>Pindah Kelas</a>
                    </div>
                </div></div>
            </div>
            <a href="{{ route('admin.api_keys.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $navApiKeys ? 'bg-primary text-white' : 'text-gray-600 hover:bg-[#e8eaf5] hover:text-primary' }}" title="API Keys" @if($navApiKeys) aria-current="page" @endif>
                <i class="ph ph-key text-xl shrink-0"></i><span class="nav-label">API Keys</span>
            </a>
        </nav>
        <div class="p-4 border-t border-gray-100 space-y-3">
            <button type="button" id="sidebar-collapse" class="hidden md:flex w-full items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-gray-500 hover:bg-gray-100 transition-colors" aria-expanded="true" aria-controls="admin-sidebar" title="Ciutkan sidebar">
                <i class="ph ph-caret-double-left text-base"></i><span class="sidebar-full">Ciutkan</span>
            </button>
            <div id="sidebar-user-row" class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center text-primary shrink-0">
                    <i class="ph ph-user text-xl"></i>
                </div>
                <div id="sidebar-user-info" class="min-w-0 flex-1">
                    <p class="font-medium text-sm text-gray-900 truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-gray-500 capitalize">{{ Auth::user()->role }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="block p-2 rounded-lg text-gray-500 hover:text-red-500 hover:bg-red-50 transition-colors" title="Logout" aria-label="Keluar">
                        <i class="ph ph-sign-out text-xl"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>
    @else
    <nav class="bg-white border-b border-gray-200 py-4 px-6 md:px-12 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo-upb.png') }}" alt="Logo Universitas" class="h-8 object-contain" onerror="this.src='https://placehold.co/100x100?text=Logo'">
            <div>
                <h1 class="font-bold text-gray-900 leading-tight">CBT Praktikum</h1>
                <p class="text-xs text-gray-500">Universitas Pelita Bangsa</p>
            </div>
        </div>
        <div class="flex items-center gap-6">
            <div class="hidden md:flex items-center gap-6 text-sm font-medium text-gray-600 mr-4">
                <a href="{{ route('student.dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
                <a href="{{ route('student.history') }}" class="hover:text-primary transition-colors">Riwayat</a>
            </div>

            <div class="hidden md:block text-right border-l border-gray-200 pl-6">
                <p class="font-medium text-sm text-gray-900">{{ Auth::user()->name }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ Auth::user()->role }}</p>
            </div>
            <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center text-primary">
                <i class="ph ph-user text-xl"></i>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-gray-500 hover:text-red-500 transition-colors" title="Logout">
                    <i class="ph ph-sign-out text-2xl"></i>
                </button>
            </form>
        </div>
    </nav>
    @endif
    @endauth

    <!-- Main Content -->
    <main id="app-main" class="flex-grow flex flex-col {{ auth()->check() && auth()->user()->role === 'admin' ? 'md:ml-72' : '' }}">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="py-6 text-center text-sm text-gray-500 mt-auto">
        &copy; {{ date('Y') }} Laboratorium Informatika Universitas Pelita Bangsa | Developed by <a href="https://instagram.com/wirarya_" class="hover:text-primary transition-colors hover:underline">Arya Wiratama</a>.
    </footer>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    <!-- KaTeX -->
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>
    
    <!-- Prism.js -->
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <script>Prism.plugins.autoloader.languages_path = 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/';</script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(document.body, {
                    delimiters: [
                        {left: '\\[', right: '\\]', display: true},
                        {left: '\\(', right: '\\)', display: false},
                        {left: '$$', right: '$$', display: true},
                        {left: '$', right: '$', display: false},
                    ],
                    throwOnError: false,
                });
            }
            if (typeof Prism !== 'undefined') {
                convertCodeBlocks();
                Prism.highlightAll();
            }
        });

        function convertCodeBlocks() {
            document.querySelectorAll('.question-content, .question-text, .whitespace-pre-wrap').forEach(el => {
                el.innerHTML = el.innerHTML.replace(
                    /```(\w*)\s*\n?([\s\S]*?)```/g,
                    '<pre class="line-numbers"><code class="language-$1">$2</code></pre>'
                );
            });
        }

        function renderCodeBlocks(text) {
            return text.replace(
                /```(\w*)\s*\n?([\s\S]*?)```/g,
                function(_, lang, code) {
                    const d = document.createElement('div');
                    d.textContent = code;
                    return '<pre class="line-numbers"><code class="language-' + (lang || 'plaintext') + '">' + d.innerHTML + '</code></pre>';
                }
            );
        }
    </script>

    <!-- SweetAlert2 for confirmations & flash messages (admin) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function swalConfirm(message, opts) {
            opts = opts || {};
            return Swal.fire({
                title: opts.title || 'Yakin?',
                text: message,
                icon: opts.icon || 'warning',
                showCancelButton: true,
                confirmButtonText: opts.confirmText || 'Ya, lanjutkan',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-[2rem]',
                    title: '!text-xl !font-bold !text-gray-900',
                    htmlContainer: '!text-sm !text-gray-600',
                    confirmButton: 'bg-primary hover:bg-primary-hover text-white font-semibold px-5 py-2.5 rounded-xl transition-colors ml-2',
                    cancelButton: 'bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-5 py-2.5 rounded-xl transition-colors'
                }
            });
        }

        // Bind directly on each form (target phase): some admin tables nest
        // row-action forms inside a bulk-action form (invalid but legacy HTML),
        // and a bubbling submit event does not reliably reach document there.
        // Direct binding behaves exactly like the old inline onsubmit confirm.
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1') return;
                e.preventDefault();
                swalConfirm(form.getAttribute('data-confirm'), {
                    title: form.getAttribute('data-confirm-title'),
                    confirmText: form.getAttribute('data-confirm-text')
                }).then(function (r) {
                    if (r.isConfirmed) {
                        form.dataset.confirmed = '1';
                        form.submit();
                    }
                });
            });
        });

        // Standalone buttons carrying data-confirm-target (e.g. bulk actions).
        document.querySelectorAll('[data-confirm-target]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                swalConfirm(el.getAttribute('data-confirm'), {
                    title: el.getAttribute('data-confirm-title'),
                    confirmText: el.getAttribute('data-confirm-text')
                }).then(function (r) {
                    if (r.isConfirmed) {
                        var target = document.querySelector(el.getAttribute('data-confirm-target'));
                        if (target) target.submit();
                    }
                });
            });
        });

        @if(request()->is('admin/*') && (session('success') || session('error')))
        document.addEventListener('DOMContentLoaded', function () {
            @if(session('success'))
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: @json(session('success')),
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            @endif
            @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Terjadi kesalahan',
                text: @json(session('error')),
                confirmButtonText: 'Mengerti',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-[2rem]',
                    title: '!text-xl !font-bold !text-gray-900',
                    htmlContainer: '!text-sm !text-gray-600',
                    confirmButton: 'bg-primary hover:bg-primary-hover text-white font-semibold px-5 py-2.5 rounded-xl transition-colors'
                }
            });
            @endif
        });
        @endif
    </script>

    <script>
        // Admin sidebar: collapse rail, dropdown groups, mobile drawer.
        // Presentational only; no app logic touched.
        (function () {
            var root = document.documentElement;
            var sidebar = document.getElementById('admin-sidebar');
            if (!sidebar) return;

            function setCollapsed(collapsed) {
                root.classList.toggle('sidebar-collapsed', collapsed);
                try { localStorage.setItem('cbt-sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {}
                var btn = document.getElementById('sidebar-collapse');
                if (btn) btn.setAttribute('aria-expanded', String(!collapsed));
            }

            var collapseBtn = document.getElementById('sidebar-collapse');
            if (collapseBtn) collapseBtn.addEventListener('click', function () {
                setCollapsed(!root.classList.contains('sidebar-collapsed'));
            });

            document.querySelectorAll('[data-group-toggle]').forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    if (root.classList.contains('sidebar-collapsed') && window.innerWidth >= 768) {
                        setCollapsed(false);
                    }
                    var group = toggle.closest('[data-group]');
                    var open = group.classList.toggle('open');
                    toggle.setAttribute('aria-expanded', String(open));
                });
            });

            var openBtn = document.getElementById('sidebar-open');
            var overlay = document.getElementById('sidebar-overlay');
            var closeBtn = document.getElementById('sidebar-close');
            function setDrawer(open) {
                sidebar.classList.toggle('-translate-x-full', !open);
                if (overlay) overlay.classList.toggle('hidden', !open);
                if (openBtn) openBtn.setAttribute('aria-expanded', String(open));
                if (window.innerWidth < 768) document.body.classList.toggle('overflow-hidden', open);
            }
            if (openBtn) openBtn.addEventListener('click', function () { setDrawer(true); });
            if (overlay) overlay.addEventListener('click', function () { setDrawer(false); });
            if (closeBtn) closeBtn.addEventListener('click', function () { setDrawer(false); });
            sidebar.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', function () { if (window.innerWidth < 768) setDrawer(false); });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && window.innerWidth < 768 && !sidebar.classList.contains('-translate-x-full')) setDrawer(false);
            });
        })();
    </script>

    @stack('scripts')

</body>
</html>
