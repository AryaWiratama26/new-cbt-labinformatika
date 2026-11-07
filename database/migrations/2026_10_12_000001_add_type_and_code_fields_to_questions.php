<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('type')->default('multiple_choice')->after('id');
            $table->string('code_language')->nullable()->after('explanation');
            $table->text('starter_code')->nullable()->after('code_language');
            $table->text('solution_code')->nullable()->after('starter_code');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['type', 'code_language', 'starter_code', 'solution_code']);
        });
    }
};
