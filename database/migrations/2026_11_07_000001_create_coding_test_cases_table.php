<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::dropIfExists('coding_test_cases');
    }
};
