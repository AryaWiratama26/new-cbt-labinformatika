<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proctor_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('viewer_id', 64);
            $table->string('sender', 10);
            $table->string('type', 20);
            $table->longText('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['exam_session_id', 'channel', 'id']);
            $table->index(['viewer_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctor_signals');
    }
};
