<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->text('answer_text')->nullable()->after('option_id');
            $table->string('grade_status')->nullable()->after('answer_text');
            $table->decimal('score', 5, 2)->nullable()->after('grade_status');
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->timestamp('graded_at')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn(['answer_text', 'grade_status', 'score']);
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropColumn('graded_at');
        });
    }
};
