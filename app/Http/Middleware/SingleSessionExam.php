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
            $storedSessionId = Cache::get($examSessionKey);

            if ($storedSessionId && $storedSessionId !== session()->getId()) {
                abort(403, 'Anda sedang mengerjakan ujian di perangkat lain. Selesaikan ujian terlebih dahulu.');
            }

            if (! $storedSessionId) {
                Cache::put($examSessionKey, session()->getId(), 86400);
            }
        }

        return $next($request);
    }
}
