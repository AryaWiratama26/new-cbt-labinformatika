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
</head>
<body class="antialiased min-h-screen flex flex-col selection:bg-primary selection:text-white">

    <!-- Top Navigation (if logged in) -->
    @auth
    <nav class="bg-white border-b border-gray-200 py-4 px-6 md:px-12 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo-upb.png') }}" alt="Logo Universitas" class="h-8 object-contain" onerror="this.src='https://placehold.co/100x100?text=Logo'">
            <div>
                <h1 class="font-bold text-gray-900 leading-tight">CBT Praktikum</h1>
                <p class="text-xs text-gray-500">Universitas Pelita Bangsa</p>
            </div>
        </div>
        <div class="flex items-center gap-6">
            @if(Auth::user()->role === 'admin')
            <div class="hidden md:flex items-center gap-6 text-sm font-medium text-gray-600 mr-4">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
                <a href="{{ route('admin.analytics') }}" class="hover:text-primary transition-colors">Analitik</a>
                <a href="{{ route('admin.courses.index') }}" class="hover:text-primary transition-colors">Mata Kuliah</a>
                <a href="{{ route('admin.exams.index') }}" class="hover:text-primary transition-colors">Ujian</a>
                <a href="{{ route('admin.students.index') }}" class="hover:text-primary transition-colors">Mahasiswa</a>
            </div>
            @elseif(Auth::user()->role === 'mahasiswa')
            <div class="hidden md:flex items-center gap-6 text-sm font-medium text-gray-600 mr-4">
                <a href="{{ route('student.dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
                <a href="{{ route('student.history') }}" class="hover:text-primary transition-colors">Riwayat</a>
            </div>
            @endif

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
    @endauth

    <!-- Main Content -->
    <main class="flex-grow flex flex-col">
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

    @stack('scripts')

</body>
</html>
