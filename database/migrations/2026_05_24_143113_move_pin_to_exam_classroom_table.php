<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_classroom', function (Blueprint $table) {
            $table->string('pin', 10)->nullable()->after('duration_minutes');
        });

        $exams = DB::table('exams')
            ->whereNotNull('pin')
            ->get(['id', 'pin']);

        foreach ($exams as $exam) {
            DB::table('exam_classroom')
                ->where('exam_id', $exam->id)
                ->update(['pin' => $exam->pin]);
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('pin');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('pin', 10)->nullable()->after('require_fullscreen');
        });

        $examIds = DB::table('exam_classroom')
            ->whereNotNull('pin')
            ->distinct()
            ->pluck('exam_id');

        foreach ($examIds as $examId) {
            $pivot = DB::table('exam_classroom')
                ->where('exam_id', $examId)
                ->whereNotNull('pin')
                ->first();

            if ($pivot) {
                DB::table('exams')
                    ->where('id', $examId)
                    ->whereNull('pin')
                    ->update(['pin' => $pivot->pin]);
            }
        }

        Schema::table('exam_classroom', function (Blueprint $table) {
            $table->dropColumn('pin');
        });
    }
};
