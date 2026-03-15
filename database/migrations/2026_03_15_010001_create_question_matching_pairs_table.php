<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_matching_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('premise');
            $table->text('response');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_matching_pairs');
    }
};
