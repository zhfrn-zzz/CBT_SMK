<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['exam_session_id', 'question_id']);
            $table->index(['exam_session_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_questions');
    }
};
