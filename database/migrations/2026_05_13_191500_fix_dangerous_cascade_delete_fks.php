<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix dangerous cascadeOnDelete foreign keys that can cause silent data loss:
     *
     * 1. answers.option_id: cascadeOnDelete → nullOnDelete
     *    (deleting an option should NOT delete all student answers)
     *
     * 2. exam_sessions.user_id: cascadeOnDelete → nullOnDelete
     *    (deleting a student should NOT destroy all exam history)
     *
     * 3. exams.course_id: cascadeOnDelete → restrictOnDelete
     *    (deleting a course should be BLOCKED if exams exist)
     *
     * 4. exams.classroom_id: cascadeOnDelete → restrictOnDelete
     *    (deleting a classroom should be BLOCKED if exams exist)
     */
    public function up(): void
    {
        // 1. Fix answers.option_id: cascadeOnDelete → nullOnDelete
        Schema::table('answers', function (Blueprint $table) {
            $table->dropForeign(['option_id']);
            $table->foreign('option_id')->references('id')->on('options')->nullOnDelete();
        });

        // 2. Fix exam_sessions.user_id: cascadeOnDelete → nullOnDelete
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            // Make column nullable first (needed for nullOnDelete to work)
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // 3. Fix exams.course_id: cascadeOnDelete → restrictOnDelete
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
        });

        // 4. Fix exams.classroom_id: cascadeOnDelete → restrictOnDelete
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Revert answers.option_id
        Schema::table('answers', function (Blueprint $table) {
            $table->dropForeign(['option_id']);
            $table->foreign('option_id')->references('id')->on('options')->cascadeOnDelete();
        });

        // Revert exam_sessions.user_id
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Revert exams.course_id
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        // Revert exams.classroom_id
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
        });
    }
};
