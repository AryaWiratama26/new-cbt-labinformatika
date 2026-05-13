<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BUG #07 fix: Tambah unique constraint pada (exam_session_id, question_id)
     * untuk mencegah duplikat jawaban akibat race condition (double-click, slow retry)
     */
    public function up(): void
    {
        // Hapus duplikat yang mungkin sudah ada sebelum menambah constraint
        $duplicates = \Illuminate\Support\Facades\DB::table('answers')
            ->select('exam_session_id', 'question_id', \Illuminate\Support\Facades\DB::raw('MIN(id) as keep_id'))
            ->groupBy('exam_session_id', 'question_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            \Illuminate\Support\Facades\DB::table('answers')
                ->where('exam_session_id', $dup->exam_session_id)
                ->where('question_id', $dup->question_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('answers', function (Blueprint $table) {
            $table->unique(['exam_session_id', 'question_id'], 'answers_session_question_unique');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropUnique('answers_session_question_unique');
        });
    }
};
