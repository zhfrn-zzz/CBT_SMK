<?php

use App\Models\ExamSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Exam proctor channel — guru who owns the exam can listen
Broadcast::channel('exam.{examSessionId}', function ($user, int $examSessionId) {
    $examSession = ExamSession::find($examSessionId);
    if (! $examSession) {
        return false;
    }

    return $user->isGuru() && $user->id === $examSession->user_id;
});

// Per-student exam channel — the student themselves can listen
Broadcast::channel('exam.{examSessionId}.student.{userId}', function ($user, int $examSessionId, int $userId) {
    return (int) $user->id === $userId;
});
