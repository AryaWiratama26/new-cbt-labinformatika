<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('camera_proctoring')->default(false)->after('require_fullscreen');
            $table->boolean('camera_phone')->default(false)->after('camera_proctoring');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['camera_proctoring', 'camera_phone']);
        });
    }
};
