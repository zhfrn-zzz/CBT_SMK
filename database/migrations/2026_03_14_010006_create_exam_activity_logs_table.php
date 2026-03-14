<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('description')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('exam_attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_activity_logs');
    }
};
