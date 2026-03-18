<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('file_path', 500)->nullable();
            $table->string('file_original_name')->nullable();
            $table->dateTime('deadline_at');
            $table->decimal('max_score', 8, 2)->default(100);
            $table->boolean('allow_late_submission')->default(false);
            $table->integer('late_penalty_percent')->default(0);
            $table->enum('submission_type', ['file', 'text', 'file_or_text']);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['subject_id', 'classroom_id']);
            $table->index('user_id');
            $table->index('deadline_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
