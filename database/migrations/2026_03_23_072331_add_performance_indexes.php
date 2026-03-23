<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §1: exam_attempts — replace (exam_session_id, user_id) with (exam_session_id, user_id, status)
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex('exam_attempts_exam_session_id_user_id_index');
            $table->index(['exam_session_id', 'user_id', 'status'], 'idx_attempt_lookup');
        });

        // §1: student_answers — replace composite index with UNIQUE constraint (required for upsert)
        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropIndex('student_answers_exam_attempt_id_question_id_index');
            $table->unique(['exam_attempt_id', 'question_id'], 'uniq_attempt_question');
        });

        // §1: exam_activity_logs — add composite index for logActivity query
        Schema::table('exam_activity_logs', function (Blueprint $table) {
            $table->index(['exam_attempt_id', 'event_type'], 'idx_attempt_event');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_attempt_lookup');
            $table->index(['exam_session_id', 'user_id']);
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropUnique('uniq_attempt_question');
            $table->index(['exam_attempt_id', 'question_id']);
        });

        Schema::table('exam_activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_attempt_event');
        });
    }
};
