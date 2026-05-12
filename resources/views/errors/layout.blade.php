<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - CBT Praktikum</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased min-h-screen bg-gray-50 flex flex-col justify-center items-center selection:bg-primary selection:text-white p-6">
    <div class="max-w-2xl w-full text-center">
        <!-- Logos -->
        <div class="flex justify-center items-center gap-6 mb-8">
            <img src="{{ asset('images/logo-upb.png') }}" alt="Pelita Bangsa" class="h-16 object-contain" onerror="this.src='https://placehold.co/100x100?text=UPB'">
            <div class="h-10 w-px bg-gray-300"></div>
            <img src="{{ asset('images/logo-aslab.jpeg') }}" alt="Aslab" class="h-16 object-contain rounded-lg" onerror="this.src='https://placehold.co/100x100?text=Aslab'">
        </div>

        <!-- Error Content -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 md:p-12 overflow-hidden relative">
            <!-- Decorative background elements -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary/5 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-red-500/5 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-red-50 rounded-full mb-6">
                    <i class="@yield('icon', 'ph-fill ph-warning-circle') text-5xl text-red-500"></i>
                </div>
                
                <h1 class="text-8xl font-black text-gray-900 tracking-tighter mb-2">@yield('code')</h1>
                <h2 class="text-2xl font-bold text-gray-800 mb-4">@yield('message')</h2>
                <p class="text-gray-500 mb-8 max-w-md mx-auto text-lg">@yield('description', 'Maaf, terjadi kesalahan pada sistem atau halaman yang Anda cari tidak dapat ditemukan.')</p>
                
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-white border border-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all focus:ring-4 focus:ring-gray-100 active:scale-95 shadow-sm">
                        <i class="ph ph-arrow-left text-lg"></i> Kembali
                    </a>
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-900 text-white font-semibold rounded-xl hover:bg-gray-800 transition-all focus:ring-4 focus:ring-gray-200 active:scale-95 shadow-md shadow-gray-900/10">
                        <i class="ph ph-house text-lg"></i> Beranda Utama
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-10 text-sm font-medium text-gray-400">
            &copy; {{ date('Y') }} CBT Praktikum Universitas Pelita Bangsa.
        </div>
    </div>
</body>
</html>
