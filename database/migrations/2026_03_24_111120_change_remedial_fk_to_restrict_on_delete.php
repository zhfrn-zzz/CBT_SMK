<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['original_exam_session_id']);
            $table->foreign('original_exam_session_id')
                ->references('id')->on('exam_sessions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['original_exam_session_id']);
            $table->foreign('original_exam_session_id')
                ->references('id')->on('exam_sessions')
                ->nullOnDelete();
        });
    }
};
