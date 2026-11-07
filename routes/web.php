<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProctorController;
use App\Http\Controllers\StudentController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\StudentMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Proctor pairing (accessible without full auth, token-based)
Route::get('/pair/{token}', [ProctorController::class, 'showPairPage'])->name('proctor.pair');
Route::post('/pair/{token}/verify', [ProctorController::class, 'verifyPairingToken'])->name('proctor.pair.verify');
Route::get('/pair/{token}/status', [ProctorController::class, 'pairingSessionStatus'])->name('proctor.pair.status');
Route::post('/pair/{token}/event', [ProctorController::class, 'postPairEvent'])->name('proctor.pair.event');

Route::middleware(['auth'])->group(function () {
    // Admin Routes
    Route::middleware([AdminMiddleware::class])->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('/admin/import-students', [AdminController::class, 'importStudents'])->name('admin.import_students');
        Route::get('/admin/template/students', [AdminController::class, 'downloadStudentTemplate'])->name('admin.template_students');
        Route::get('/admin/template/questions', [AdminController::class, 'downloadQuestionTemplate'])->name('admin.template_questions');
        Route::get('/admin/template/questions-docx', [ModuleController::class, 'downloadDocxTemplate'])->name('admin.template_questions.docx');

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
        Route::resource('admin/courses', CourseController::class)->only(['index', 'store', 'destroy'])->names('admin.courses');

        // Module routes nested under courses
        Route::prefix('admin/courses/{course}/modules')->name('admin.courses.modules.')->group(function () {
            Route::get('/', [ModuleController::class, 'index'])->name('index');
            Route::get('/create', [ModuleController::class, 'create'])->name('create');
            Route::post('/', [ModuleController::class, 'store'])->name('store');
            Route::get('/{module}', [ModuleController::class, 'show'])->name('show');
            Route::delete('/{module}', [ModuleController::class, 'destroy'])->name('destroy');

            // Questions inside a module
            Route::post('/{module}/import-questions', [ModuleController::class, 'importQuestions'])->name('import_questions');
            Route::post('/{module}/import-docx', [ModuleController::class, 'importQuestionsDocx'])->name('import_questions.docx');
            Route::get('/{module}/questions/create', [ModuleController::class, 'createQuestion'])->name('questions.create');
            Route::post('/{module}/questions', [ModuleController::class, 'storeQuestion'])->name('questions.store');
            Route::get('/{module}/questions/{question}/edit', [ModuleController::class, 'editQuestion'])->name('questions.edit');
            Route::put('/{module}/questions/{question}', [ModuleController::class, 'updateQuestion'])->name('questions.update');
            Route::post('/{module}/questions/{question}/duplicate', [ModuleController::class, 'duplicateQuestion'])->name('questions.duplicate');
            Route::delete('/{module}/questions/{question}', [ModuleController::class, 'destroyQuestion'])->name('questions.destroy');
            Route::post('/{module}/generate-question', [ModuleController::class, 'generateQuestion'])->name('questions.generate');
            Route::post('/{module}/questions/save-generated', [ModuleController::class, 'saveGeneratedQuestion'])->name('questions.save_generated');
        });

        // Exams (schedule)
        Route::resource('admin/exams', ExamController::class)->names('admin.exams');
        Route::get('admin/analytics', [AdminController::class, 'analytics'])->name('admin.analytics');
        Route::get('admin/exams/{exam}/results', [ExamController::class, 'results'])->name('admin.exams.results');
        Route::get('admin/exams/{exam}/monitor', [ExamController::class, 'monitor'])->name('admin.exams.monitor');
        Route::get('admin/exams/{exam}/pdf', [ExamController::class, 'exportPdf'])->name('admin.exams.pdf');
        Route::get('admin/exams/{exam}/results/csv', [ExamController::class, 'resultsCsv'])->name('admin.exams.results.csv');
        Route::get('admin/exams/{exam}/student/{user}/report', [ExamController::class, 'studentReport'])->name('admin.exams.student_report');
        Route::post('admin/exams/{exam}/sessions/{session}/grade-coding', [ExamController::class, 'gradeCoding'])->name('admin.exams.grade_coding');
        Route::post('admin/exams/{exam}/sessions/{session}/ai-grade-coding', [ExamController::class, 'aiGradeCoding'])->name('admin.exams.ai_grade_coding');
        Route::post('admin/exams/{exam}/run-code', [ExamController::class, 'runCode'])->name('admin.exams.run_code');

        // Classroom recap
        Route::get('admin/classrooms/{classroom}/recap', [AdminController::class, 'classroomRecap'])->name('admin.classrooms.recap');
        Route::get('admin/classrooms/{classroom}/recap/csv', [AdminController::class, 'classroomRecapCsv'])->name('admin.classrooms.recap.csv');

        // Proctoring (admin)
        Route::get('admin/sessions/{session}/proctor-events', [ProctorController::class, 'getEvents'])->name('admin.proctor.events');
    });

    // Student Routes
    Route::middleware([StudentMiddleware::class])->group(function () {
        Route::get('/student/dashboard', [StudentController::class, 'dashboard'])->name('student.dashboard');
        Route::get('/student/exams/{exam}', [StudentController::class, 'show'])->name('student.exams.show');
        Route::post('/student/exams/{exam}/start', [StudentController::class, 'start'])->name('student.exams.start');
        Route::post('/student/exams/{exam}/verify-pin', [StudentController::class, 'verifyPin'])->name('student.exams.verify_pin')->middleware('throttle:5,1');
        Route::get('/student/exams/{exam}/attempt', [StudentController::class, 'attempt'])->name('student.exams.attempt');
        Route::post('/student/exams/{exam}/submit', [StudentController::class, 'submit'])->name('student.exams.submit');
        Route::post('/student/exams/{exam}/save-answer', [StudentController::class, 'saveAnswer'])->name('student.exams.save_answer')->middleware('throttle:60,1');
        Route::post('/student/exams/{exam}/run-code', [StudentController::class, 'runCode'])->name('student.exams.run_code')->middleware('throttle:20,1');
        Route::post('/student/exams/{exam}/tab-switch', [StudentController::class, 'reportTabSwitch'])->name('student.exams.tab_switch')->middleware('throttle:60,1');
        Route::post('/student/exams/{exam}/sync', [StudentController::class, 'syncAnswers'])->name('student.exams.sync')->middleware('throttle:60,1');

        // Proctoring (student)
        Route::post('/student/exams/{exam}/proctor/pair-token', [ProctorController::class, 'createPairingToken'])->name('proctor.pair_token');
        Route::get('/student/exams/{exam}/proctor/pair-status', [ProctorController::class, 'pairingStatus'])->name('proctor.pair_status');
        Route::post('/student/exams/{exam}/proctor/camera-ready', [ProctorController::class, 'markCameraReady'])->name('proctor.camera_ready');
        Route::post('/proctor/signal', [ProctorController::class, 'postSignal'])->name('proctor.signal');
        Route::get('/proctor/signal/poll', [ProctorController::class, 'pollSignals'])->name('proctor.signal.poll');
        Route::post('/proctor/event', [ProctorController::class, 'postEvent'])->name('proctor.event');

        // Student history & review
        Route::get('/student/history', [StudentController::class, 'history'])->name('student.history');
        Route::get('/student/history/{examSession}', [StudentController::class, 'reviewSession'])->name('student.history.review');
        Route::get('/student/history/{examSession}/grade-status', [StudentController::class, 'gradeStatus'])->name('student.history.grade_status');
    });
});
