<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MIG-01 fix: Ubah questions.exam_id FK dari cascadeOnDelete → nullOnDelete.
     * 
     * Setelah refactor ke module-based questions, exam_id hanya dipakai
     * sebagai optional link. Menghapus exam seharusnya TIDAK menghapus soalnya,
     * karena soal sekarang dimiliki oleh module.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Drop FK lama (cascadeOnDelete)
            $table->dropForeign(['exam_id']);
            // Buat FK baru dengan nullOnDelete
            $table->foreign('exam_id')->references('id')->on('exams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });
    }
};
