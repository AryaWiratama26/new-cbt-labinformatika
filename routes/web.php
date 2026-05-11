<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    // Admin Routes
    Route::middleware([\App\Http\Middleware\AdminMiddleware::class])->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('/admin/import-students', [AdminController::class, 'importStudents'])->name('admin.import_students');
        Route::get('/admin/template/students', [AdminController::class, 'downloadStudentTemplate'])->name('admin.template_students');
        Route::get('/admin/template/questions', [AdminController::class, 'downloadQuestionTemplate'])->name('admin.template_questions');

        // Students management
        Route::get('/admin/students', [AdminController::class, 'students'])->name('admin.students.index');
        Route::get('/admin/students/create', [AdminController::class, 'createStudent'])->name('admin.students.create');
        Route::post('/admin/students', [AdminController::class, 'storeStudent'])->name('admin.students.store');
        Route::get('/admin/students/{user}/edit', [AdminController::class, 'editStudent'])->name('admin.students.edit');
        Route::put('/admin/students/{user}', [AdminController::class, 'updateStudent'])->name('admin.students.update');
        Route::post('/admin/students/{user}/reset-password', [AdminController::class, 'resetPassword'])->name('admin.students.reset_password');
        Route::delete('/admin/students/{user}', [AdminController::class, 'destroyStudent'])->name('admin.students.destroy');
        Route::post('/admin/students/bulk-delete', [AdminController::class, 'bulkDeleteStudents'])->name('admin.students.bulk_delete');
        Route::get('/admin/students/export', [AdminController::class, 'exportStudents'])->name('admin.students.export');
        Route::get('/admin/students/move', [AdminController::class, 'moveStudentsForm'])->name('admin.students.move.form');
        Route::post('/admin/students/move', [AdminController::class, 'moveStudents'])->name('admin.students.move');

        // Classrooms
        Route::get('/admin/classrooms', [AdminController::class, 'classrooms'])->name('admin.classrooms');
        Route::post('/admin/classrooms', [AdminController::class, 'storeClassroom'])->name('admin.classrooms.store');
        Route::put('/admin/classrooms/{classroom}', [AdminController::class, 'updateClassroom'])->name('admin.classrooms.update');
        Route::delete('/admin/classrooms/{classroom}', [AdminController::class, 'destroyClassroom'])->name('admin.classrooms.destroy');

        // Courses (with nested Modules)
        Route::resource('admin/courses', \App\Http\Controllers\CourseController::class)->names('admin.courses');

        // Module routes nested under courses
        Route::prefix('admin/courses/{course}/modules')->name('admin.courses.modules.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ModuleController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\ModuleController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\ModuleController::class, 'store'])->name('store');
            Route::get('/{module}', [\App\Http\Controllers\ModuleController::class, 'show'])->name('show');
            Route::delete('/{module}', [\App\Http\Controllers\ModuleController::class, 'destroy'])->name('destroy');

            // Questions inside a module
            Route::post('/{module}/import-questions', [\App\Http\Controllers\ModuleController::class, 'importQuestions'])->name('import_questions');
            Route::get('/{module}/questions/create', [\App\Http\Controllers\ModuleController::class, 'createQuestion'])->name('questions.create');
            Route::post('/{module}/questions', [\App\Http\Controllers\ModuleController::class, 'storeQuestion'])->name('questions.store');
            Route::get('/{module}/questions/{question}/edit', [\App\Http\Controllers\ModuleController::class, 'editQuestion'])->name('questions.edit');
            Route::put('/{module}/questions/{question}', [\App\Http\Controllers\ModuleController::class, 'updateQuestion'])->name('questions.update');
            Route::post('/{module}/questions/{question}/duplicate', [\App\Http\Controllers\ModuleController::class, 'duplicateQuestion'])->name('questions.duplicate');
            Route::delete('/{module}/questions/{question}', [\App\Http\Controllers\ModuleController::class, 'destroyQuestion'])->name('questions.destroy');
        });

        // Exams (schedule)
        Route::resource('admin/exams', \App\Http\Controllers\ExamController::class)->names('admin.exams');
        Route::get('admin/exams/{exam}/results', [\App\Http\Controllers\ExamController::class, 'results'])->name('admin.exams.results');
    });

    // Student Routes
    Route::middleware([\App\Http\Middleware\StudentMiddleware::class])->group(function () {
        Route::get('/student/dashboard', [\App\Http\Controllers\StudentController::class, 'dashboard'])->name('student.dashboard');
        Route::get('/student/exams/{exam}', [\App\Http\Controllers\StudentController::class, 'show'])->name('student.exams.show');
        Route::post('/student/exams/{exam}/start', [\App\Http\Controllers\StudentController::class, 'start'])->name('student.exams.start');
        Route::get('/student/exams/{exam}/attempt', [\App\Http\Controllers\StudentController::class, 'attempt'])->name('student.exams.attempt');
        Route::post('/student/exams/{exam}/submit', [\App\Http\Controllers\StudentController::class, 'submit'])->name('student.exams.submit');
    });
});
