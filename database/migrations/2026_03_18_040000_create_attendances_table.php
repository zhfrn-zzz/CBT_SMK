<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('meeting_date');
            $table->integer('meeting_number');
            $table->string('access_code', 6)->nullable();
            $table->dateTime('code_expires_at')->nullable();
            $table->boolean('is_open')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'subject_id', 'meeting_date']);
            $table->index(['classroom_id', 'subject_id']);
            $table->index('user_id');
            $table->index('meeting_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
