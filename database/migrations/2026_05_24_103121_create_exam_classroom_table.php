<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_classroom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->integer('duration_minutes');
            $table->unique(['exam_id', 'classroom_id']);
            $table->timestamps();
        });

        $exams = DB::table('exams')
            ->whereNotNull('classroom_id')
            ->get(['id', 'classroom_id', 'start_time', 'end_time', 'duration_minutes']);

        foreach ($exams as $exam) {
            DB::table('exam_classroom')->insert([
                'exam_id' => $exam->id,
                'classroom_id' => $exam->classroom_id,
                'start_time' => $exam->start_time,
                'end_time' => $exam->end_time,
                'duration_minutes' => $exam->duration_minutes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'duration_minutes']);
        });

        Schema::table('exams', function (Blueprint $table) {
            $fk = DB::selectOne("
                SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exams'
                AND COLUMN_NAME = 'classroom_id' AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");

            if ($fk) {
                DB::statement("ALTER TABLE `exams` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }

            $table->dropColumn('classroom_id');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('course_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_time')->nullable()->after('classroom_id');
            $table->dateTime('end_time')->nullable()->after('start_time');
            $table->integer('duration_minutes')->nullable()->after('end_time');
        });

        $pivots = DB::table('exam_classroom')->get();

        foreach ($pivots as $pivot) {
            DB::table('exams')
                ->where('id', $pivot->exam_id)
                ->whereNull('classroom_id')
                ->update([
                    'classroom_id' => $pivot->classroom_id,
                    'start_time' => $pivot->start_time,
                    'end_time' => $pivot->end_time,
                    'duration_minutes' => $pivot->duration_minutes,
                ]);
        }

        Schema::dropIfExists('exam_classroom');
    }
};
