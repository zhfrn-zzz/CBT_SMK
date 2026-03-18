<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->dateTime('last_reply_at')->nullable();
            $table->integer('reply_count')->default(0);
            $table->timestamps();

            $table->index(['subject_id', 'classroom_id']);
            $table->index('user_id');
            $table->index(['is_pinned', 'last_reply_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_threads');
    }
};
