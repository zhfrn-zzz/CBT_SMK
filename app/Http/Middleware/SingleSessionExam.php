<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SingleSessionExam
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $activeAttempt = $user->activeExamAttempt();

        if ($activeAttempt) {
            $examSessionKey = "exam_session:{$activeAttempt->id}:session_id";
            $currentSessionId = session()->getId();

            // Recover from encrypted session if cache was cleared (7.1: tamper-proof fallback)
            $sessionLockKey = "exam_lock:{$activeAttempt->id}";
            $sessionStoredId = session()->get($sessionLockKey);

            // Atomic set-if-not-exists: prevents TOCTOU race condition
            $wasSet = Cache::add($examSessionKey, $currentSessionId, 86400);

            if (! $wasSet) {
                // Key already existed — verify it matches current session
                $storedSessionId = Cache::get($examSessionKey);
                if ($storedSessionId && $storedSessionId !== $currentSessionId) {
                    abort(403, 'Anda sedang mengerjakan ujian di perangkat lain. Selesaikan ujian terlebih dahulu.');
                }
            } elseif ($sessionStoredId && $sessionStoredId !== $currentSessionId) {
                // Cache was empty but session has a lock from a different session — restore
                Cache::put($examSessionKey, $sessionStoredId, 86400);
                abort(403, 'Anda sedang mengerjakan ujian di perangkat lain. Selesaikan ujian terlebih dahulu.');
            }
        }

        return $next($request);
    }
}
