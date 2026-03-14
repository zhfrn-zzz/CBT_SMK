<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->string('token', 10)->unique();
            $table->integer('duration_minutes');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_randomize_questions')->default(false);
            $table->boolean('is_randomize_options')->default(false);
            $table->boolean('is_published')->default(false);
            $table->integer('pool_count')->nullable();
            $table->decimal('kkm', 5, 2)->nullable();
            $table->integer('max_tab_switches')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
