<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('coding_test_cases');

        Schema::table('answers', function (Blueprint $table) {
            $table->json('ai_grade_detail')->nullable()->after('graded_by');
        });

        if (Schema::hasColumn('answers', 'auto_grade_detail')) {
            Schema::table('answers', function (Blueprint $table) {
                $table->dropColumn('auto_grade_detail');
            });
        }

        DB::table('answers')->where('graded_by', 'auto')->update(['graded_by' => 'ai']);
    }

    public function down(): void
    {
        Schema::create('coding_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->text('stdin')->nullable();
            $table->text('expected_output');
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_hidden')->default(true);
            $table->boolean('is_exact')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['question_id', 'position']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->json('auto_grade_detail')->nullable()->after('ai_grade_detail');
        });
    }
};
