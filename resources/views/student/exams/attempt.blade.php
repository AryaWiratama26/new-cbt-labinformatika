<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ujian CBT - {{ $exam->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="text-gray-800 antialiased h-screen flex flex-col overflow-hidden">

    <!-- Top Navigation for Exam -->
    <header class="bg-white border-b border-gray-200 py-4 px-6 flex-shrink-0 z-10">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="h-10 w-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary font-bold">
                    <i class="ph-fill ph-student text-xl"></i>
                </div>
                <div>
                    <h1 class="font-bold text-gray-900 leading-tight">{{ $exam->title }}</h1>
                    <p class="text-xs text-gray-500">{{ auth()->user()->name }} ({{ auth()->user()->username }})</p>
                </div>
            </div>

            <!-- Timer -->
            <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-2 rounded-xl flex items-center gap-3 font-mono font-bold text-lg">
                <i class="ph ph-timer text-xl"></i>
                <span id="countdown-timer">00:00:00</span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex overflow-hidden max-w-7xl mx-auto w-full">
        
        <!-- Questions Area -->
        <div class="flex-grow overflow-y-auto p-6 md:p-8 scroll-smooth" id="questions-container">
            <form id="exam-form" action="{{ route('student.exams.submit', $exam) }}" method="POST">
                @csrf
                
                <div class="space-y-12 max-w-3xl mx-auto pb-24">
                    @foreach($questions as $index => $question)
                        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 question-card" id="q-{{ $question->id }}">
                            <div class="flex gap-4 mb-6">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-lg border border-primary/20">
                                    {{ $index + 1 }}
                                </div>
                                <div class="flex-grow pt-1.5">
                                    <p class="text-gray-900 text-lg font-medium leading-relaxed whitespace-pre-wrap">{{ $question->content }}</p>
                                </div>
                            </div>

                            @if($question->image)
                                <div class="ml-14 mb-6">
                                    <img src="{{ asset('storage/' . $question->image) }}" alt="Gambar Soal" class="max-h-64 rounded-xl border border-gray-200 p-1 bg-gray-50">
                                </div>
                            @endif

                            <div class="ml-14 space-y-3">
                                @foreach($question->options as $option)
                                    @php
                                        $isChecked = isset($existingAnswers[$question->id]) && $existingAnswers[$question->id] == $option->id;
                                    @endphp
                                    <label class="flex items-center p-4 border rounded-xl cursor-pointer transition-colors hover:bg-gray-50 option-label {{ $isChecked ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-gray-200' }}">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}" class="w-5 h-5 text-primary border-gray-300 focus:ring-primary focus:ring-2 option-radio" {{ $isChecked ? 'checked' : '' }}>
                                        <span class="ml-3 text-gray-700">{{ $option->content }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </form>
        </div>

        <!-- Sidebar Navigation (Optional/Desktop only) -->
        <div class="hidden lg:block w-80 flex-shrink-0 border-l border-gray-200 bg-white p-6 overflow-y-auto">
            <h3 class="font-bold text-gray-900 mb-4">Navigasi Soal</h3>
            <div class="grid grid-cols-5 gap-2">
                @foreach($questions as $index => $question)
                    @php
                        $isAnswered = isset($existingAnswers[$question->id]);
                    @endphp
                    <a href="#q-{{ $question->id }}" class="nav-btn flex items-center justify-center h-10 rounded-lg text-sm font-semibold transition-colors border {{ $isAnswered ? 'bg-[#3b4d3b] text-white border-[#3b4d3b]' : 'bg-white text-gray-600 border-gray-200 hover:border-primary hover:text-primary' }}" data-qid="{{ $question->id }}">
                        {{ $index + 1 }}
                    </a>
                @endforeach
            </div>

            <div class="mt-12">
                <button type="button" onclick="confirmSubmit()" class="w-full bg-[#3b4d3b] hover:bg-[#2d3b2d] text-white py-3.5 rounded-xl font-bold transition-colors flex justify-center items-center gap-2 shadow-sm">
                    <i class="ph-fill ph-check-circle text-xl"></i> Kumpulkan Ujian
                </button>
            </div>
        </div>

    </main>

    <!-- Mobile Submit Button (Sticky Bottom) -->
    <div class="lg:hidden fixed bottom-0 left-0 right-0 p-4 bg-white border-t border-gray-200 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)] z-20">
        <button type="button" onclick="confirmSubmit()" class="w-full bg-[#3b4d3b] hover:bg-[#2d3b2d] text-white py-3.5 rounded-xl font-bold transition-colors flex justify-center items-center gap-2">
            <i class="ph-fill ph-check-circle text-xl"></i> Kumpulkan Ujian
        </button>
    </div>

    <!-- Logic Script -->
    <script>
        // End Time provided from controller
        const endTimeStr = "{{ $endTime->toIso8601String() }}";
        const endTime = new Date(endTimeStr).getTime();
        
        const timerElement = document.getElementById('countdown-timer');
        const form = document.getElementById('exam-form');

        // Update Nav Buttons visual state when radio is clicked
        const radios = document.querySelectorAll('.option-radio');
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Update styling of labels in this question
                const questionDiv = this.closest('.question-card');
                questionDiv.querySelectorAll('.option-label').forEach(label => {
                    label.classList.remove('border-primary', 'bg-primary/5', 'ring-1', 'ring-primary');
                    label.classList.add('border-gray-200');
                });
                
                if(this.checked) {
                    const label = this.closest('.option-label');
                    label.classList.remove('border-gray-200');
                    label.classList.add('border-primary', 'bg-primary/5', 'ring-1', 'ring-primary');
                    
                    // Update Nav Button
                    const qid = this.name.match(/\[(.*?)\]/)[1];
                    const navBtn = document.querySelector(`.nav-btn[data-qid="${qid}"]`);
                    if(navBtn) {
                        navBtn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
                        navBtn.classList.add('bg-[#3b4d3b]', 'text-white', 'border-[#3b4d3b]');
                    }
                }
            });
        });

        // Timer Logic
        const x = setInterval(function() {
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance <= 0) {
                clearInterval(x);
                timerElement.innerHTML = "00:00:00";
                alert('Waktu ujian telah habis! Sistem akan otomatis menyimpan jawaban Anda.');
                form.submit();
                return;
            }

            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            const h = hours < 10 ? "0" + hours : hours;
            const m = minutes < 10 ? "0" + minutes : minutes;
            const s = seconds < 10 ? "0" + seconds : seconds;

            timerElement.innerHTML = h + ":" + m + ":" + s;
            
            // Warning style if < 5 minutes
            if(distance < 5 * 60 * 1000) {
                timerElement.parentElement.classList.add('animate-pulse');
            }
        }, 1000);

        function confirmSubmit() {
            if(confirm('Apakah Anda yakin ingin mengumpulkan ujian ini? Pastikan semua soal telah terjawab.')) {
                form.submit();
            }
        }
    </script>
</body>
</html>
