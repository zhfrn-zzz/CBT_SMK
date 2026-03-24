<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_session_id',
        'user_id',
        'started_at',
        'submitted_at',
        'remaining_seconds',
        'is_force_submitted',
        'ip_address',
        'device_fingerprint',
        'user_agent',
        'score',
        'is_fully_graded',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExamAttemptStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'is_force_submitted' => 'boolean',
            'is_fully_graded' => 'boolean',
            'score' => 'decimal:2',
            'remaining_seconds' => 'integer',
        ];
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(ExamAttemptQuestion::class)->orderBy('order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ExamActivityLog::class);
    }

    /**
     * Calculate remaining seconds based on current time.
     */
    public function calculateRemainingSeconds(): int
    {
        if ($this->status !== ExamAttemptStatus::InProgress) {
            return 0;
        }

        $elapsed = (int) $this->started_at->diffInSeconds(now());
        $total = $this->examSession->duration_minutes * 60;
        $remaining = $total - $elapsed;

        return max(0, (int) $remaining);
    }

    /**
     * Grace period (seconds) to allow submissions near expiration (network latency).
     */
    public const SUBMISSION_GRACE_PERIOD = 3;

    /**
     * Get raw remaining seconds (may be negative).
     */
    public function getRawRemainingSeconds(): int
    {
        if ($this->status !== ExamAttemptStatus::InProgress) {
            return 0;
        }

        $elapsed = (int) $this->started_at->diffInSeconds(now());
        $total = $this->examSession->duration_minutes * 60;

        return $total - $elapsed;
    }

    public function isExpired(): bool
    {
        return $this->calculateRemainingSeconds() <= 0;
    }

    /**
     * Check expiration with grace period for late-arriving submissions.
     */
    public function isExpiredWithGrace(): bool
    {
        return ($this->getRawRemainingSeconds() + self::SUBMISSION_GRACE_PERIOD) <= 0;
    }
}
