<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §1: exam_attempts — add composite index covering status (keep existing index for FK)
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->index(['exam_session_id', 'user_id', 'status'], 'idx_attempt_lookup');
        });

        // §1: student_answers — UNIQUE constraint now in base migration (skip if exists)
        if (! $this->hasUniqueIndex()) {
            Schema::table('student_answers', function (Blueprint $table) {
                $table->unique(['exam_attempt_id', 'question_id'], 'uniq_attempt_question');
            });
        }

        // §1: exam_activity_logs — add composite index for logActivity query
        Schema::table('exam_activity_logs', function (Blueprint $table) {
            $table->index(['exam_attempt_id', 'event_type'], 'idx_attempt_event');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_attempt_lookup');
        });

        Schema::table('exam_activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_attempt_event');
        });
    }

    private function hasUniqueIndex(): bool
    {
        $indexes = Schema::getIndexes('student_answers');
        foreach ($indexes as $index) {
            if ($index['name'] === 'uniq_attempt_question') {
                return true;
            }
        }

        return false;
    }
};
