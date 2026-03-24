<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('subject_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_banks');
    }
};
