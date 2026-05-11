@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto w-full px-6 py-8">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.exams.index') }}" class="h-10 w-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-colors">
            <i class="ph ph-arrow-left text-xl"></i>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Edit Ujian</h2>
            <p class="text-gray-500">Ubah detail ujian praktikum.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700">
            <ul class="list-disc pl-5 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <form action="{{ route('admin.exams.update', $exam) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Kiri -->
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Judul Ujian <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title', $exam->title) }}" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Modul Praktikum <span class="text-red-500">*</span></label>
                        <select name="module_id" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                            <option value="">-- Pilih Modul --</option>
                            @foreach($modules->groupBy('course.name') as $courseName => $courseModules)
                                <optgroup label="{{ $courseName }}">
                                    @foreach($courseModules as $module)
                                        <option value="{{ $module->id }}" {{ old('module_id', $exam->module_id) == $module->id ? 'selected' : '' }}>
                                            {{ $module->full_name }} ({{ $module->questions_count }} soal)
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kelas Tujuan <span class="text-red-500">*</span></label>
                        <select name="classroom_id" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach($classrooms as $classroom)
                                <option value="{{ $classroom->id }}" {{ old('classroom_id', $exam->classroom_id) == $classroom->id ? 'selected' : '' }}>{{ $classroom->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Kanan -->
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Waktu Mulai <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="start_time" value="{{ old('start_time', $exam->start_time->format('Y-m-d\TH:i')) }}" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Waktu Selesai <span class="text-red-500">*</span></label>
                        <input type="datetime-local" name="end_time" value="{{ old('end_time', $exam->end_time->format('Y-m-d\TH:i')) }}" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Durasi Pengerjaan (Menit) <span class="text-red-500">*</span></label>
                        <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $exam->duration_minutes) }}" min="1" required class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi / Aturan (Opsional)</label>
                <textarea name="description" rows="3" class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">{{ old('description', $exam->description) }}</textarea>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                <input type="hidden" name="is_active" value="0">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $exam->is_active) ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    <span class="ml-3 text-sm font-medium text-gray-700">Aktifkan Ujian Langsung</span>
                </label>
            </div>

            <div class="pt-6">
                <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white py-3.5 rounded-xl font-medium transition-colors text-lg flex justify-center items-center gap-2">
                    <i class="ph ph-floppy-disk"></i> Update Ujian
                </button>
            </div>

        </form>
    </div>
</div>
@endsection
