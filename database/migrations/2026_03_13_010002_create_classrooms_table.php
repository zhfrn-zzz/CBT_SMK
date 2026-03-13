<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "XI TKJ 1"
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('grade_level'); // '10', '11', '12'
            $table->timestamps();

            $table->index('academic_year_id');
            $table->index('department_id');
            $table->index('grade_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
