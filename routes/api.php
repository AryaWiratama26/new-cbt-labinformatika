<?php

use App\Http\Controllers\Api\GradeController;
use Illuminate\Support\Facades\Route;

/*
 * Read-only grades API for external campus systems.
 * Auth: X-API-Key header, managed from Admin > API Keys.
 */

Route::middleware(['api.key:grades:read', 'throttle:60,1'])->prefix('v1')->group(function () {
    Route::get('/courses', [GradeController::class, 'courses']);
    Route::get('/classrooms', [GradeController::class, 'classrooms']);
    Route::get('/exams', [GradeController::class, 'exams']);
    Route::get('/exams/{exam}/grades', [GradeController::class, 'examGrades']);
    Route::get('/classrooms/{classroom}/recap', [GradeController::class, 'classroomRecap']);
    Route::get('/students/{nim}/grades', [GradeController::class, 'studentGrades']);
});
