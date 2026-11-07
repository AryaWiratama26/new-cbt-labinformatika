<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->string('graded_by')->nullable()->after('score');
            $table->json('auto_grade_detail')->nullable()->after('graded_by');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn(['graded_by', 'auto_grade_detail']);
        });
    }
};
