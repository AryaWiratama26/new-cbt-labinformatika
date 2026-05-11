@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto w-full px-6 py-8">
    
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-1">Admin Dashboard</h2>
            <p class="text-gray-500">Kelola data mahasiswa dan ujian CBT.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center gap-3">
            <i class="ph ph-check-circle text-xl"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
        <!-- Stat Card 1 -->
        <a href="{{ route('admin.students.index') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4 hover:border-blue-300 hover:shadow-md transition-all group cursor-pointer">
            <div class="h-14 w-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 flex-shrink-0 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                <i class="ph ph-users text-2xl"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1 group-hover:text-blue-600 transition-colors">Mahasiswa</p>
                <p class="text-2xl font-bold text-gray-900">{{ $studentsCount }}</p>
            </div>
        </a>
        
        <!-- Stat Card 2 -->
        <a href="{{ route('admin.courses.index') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4 hover:border-purple-300 hover:shadow-md transition-all group cursor-pointer">
            <div class="h-14 w-14 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 flex-shrink-0 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                <i class="ph ph-books text-2xl"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1 group-hover:text-purple-600 transition-colors">Mata Kuliah</p>
                <p class="text-2xl font-bold text-gray-900">{{ $coursesCount }}</p>
            </div>
        </a>

        <!-- Stat Card 3 -->
        <a href="{{ route('admin.classrooms') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4 hover:border-orange-300 hover:shadow-md transition-all group cursor-pointer">
            <div class="h-14 w-14 rounded-full bg-orange-50 flex items-center justify-center text-orange-600 flex-shrink-0 group-hover:bg-orange-600 group-hover:text-white transition-colors">
                <i class="ph ph-chalkboard-teacher text-2xl"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1 group-hover:text-orange-600 transition-colors">Total Kelas</p>
                <p class="text-2xl font-bold text-gray-900">{{ $classroomsCount }}</p>
            </div>
        </a>

        <!-- Stat Card 4 -->
        <a href="{{ route('admin.exams.index') }}" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4 hover:border-green-300 hover:shadow-md transition-all group cursor-pointer">
            <div class="h-14 w-14 rounded-full bg-green-50 flex items-center justify-center text-green-600 flex-shrink-0 group-hover:bg-green-600 group-hover:text-white transition-colors">
                <i class="ph ph-exam text-2xl"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1 group-hover:text-green-600 transition-colors">Total Ujian</p>
                <p class="text-2xl font-bold text-gray-900">{{ $examsCount }}</p>
            </div>
        </a>
    </div>

    <div class="grid md:grid-cols-2 gap-8">
        <!-- Import Students Module -->
        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
            <div class="flex justify-between items-start mb-2">
                <h3 class="text-xl font-bold text-gray-900">Import Data Mahasiswa</h3>
                <a href="{{ route('admin.template_students') }}" class="text-sm text-primary hover:underline flex items-center gap-1 font-medium bg-green-50 px-3 py-1 rounded-lg">
                    <i class="ph ph-download-simple"></i> Template CSV
                </a>
            </div>
            <p class="text-sm text-gray-500 mb-6">Unggah file CSV dengan format <code>NIM, Nama, Kelas</code>. Password otomatis diset = NIM.</p>
            
            <form action="{{ route('admin.import_students') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center hover:bg-gray-50 transition-colors">
                    <i class="ph ph-upload-simple text-4xl text-gray-400 mb-2"></i>
                    <p class="text-sm text-gray-600 mb-2">Klik atau drag file CSV ke sini</p>
                    <input type="file" name="csv_file" accept=".csv" required class="block w-full text-sm text-slate-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-full file:border-0
                        file:text-sm file:font-semibold
                        file:bg-green-50 file:text-green-700
                        hover:file:bg-green-100
                        cursor-pointer">
                </div>
                @error('csv_file')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                
                <button type="submit" class="w-full bg-[#3b4d3b] hover:bg-[#2d3b2d] text-white py-3 rounded-xl font-medium transition-colors flex justify-center items-center gap-2">
                    <i class="ph ph-database"></i> Import Sekarang
                </button>
            </form>
        </div>
        
        <!-- Quick Links -->
        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Akses Cepat</h3>
            <p class="text-sm text-gray-500 mb-6">Jalan pintas ke berbagai modul utama sistem.</p>
            
            <div class="space-y-3 flex-grow">
                <a href="{{ route('admin.exams.index') }}" class="group flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-green-200 hover:bg-green-50 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 bg-green-100 text-green-600 rounded-lg flex items-center justify-center">
                            <i class="ph ph-exam text-xl"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 group-hover:text-green-800">Manajemen Ujian</p>
                            <p class="text-xs text-gray-500">Buat ujian & kelola bank soal</p>
                        </div>
                    </div>
                    <i class="ph ph-caret-right text-gray-400 group-hover:text-green-600"></i>
                </a>

                <a href="{{ route('admin.courses.index') }}" class="group flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                            <i class="ph ph-books text-xl"></i>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 group-hover:text-purple-800">Mata Kuliah</p>
                            <p class="text-xs text-gray-500">Daftar & modul matkul</p>
                        </div>
                    </div>
                    <i class="ph ph-caret-right text-gray-400 group-hover:text-purple-600"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
