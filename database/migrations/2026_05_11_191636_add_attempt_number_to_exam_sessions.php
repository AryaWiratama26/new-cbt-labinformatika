<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['exam_id']);
            $table->integer('attempt_number')->default(1);
            $table->unique(['user_id', 'exam_id', 'attempt_number']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['exam_id']);
            $table->dropUnique(['user_id', 'exam_id', 'attempt_number']);
            $table->dropColumn('attempt_number');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });
    }
};
