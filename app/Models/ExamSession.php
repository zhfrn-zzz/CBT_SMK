<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'subject_id',
        'user_id',
        'academic_year_id',
        'question_bank_id',
        'token',
        'duration_minutes',
        'starts_at',
        'ends_at',
        'is_randomize_questions',
        'is_randomize_options',
        'is_published',
        'is_results_published',
        'pool_count',
        'kkm',
        'max_tab_switches',
        'is_device_lock_enabled',
        'status',
        'original_exam_session_id',
        'remedial_policy',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExamStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_randomize_questions' => 'boolean',
            'is_randomize_options' => 'boolean',
            'is_published' => 'boolean',
            'is_results_published' => 'boolean',
            'is_device_lock_enabled' => 'boolean',
            'pool_count' => 'integer',
            'kkm' => 'decimal:2',
            'max_tab_switches' => 'integer',
            'duration_minutes' => 'integer',
            'original_exam_session_id' => 'integer',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'exam_session_classroom')
            ->withTimestamps();
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_session_questions')
            ->withPivot('order')
            ->orderByPivot('order')
            ->withTimestamps();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function originalExamSession(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_exam_session_id');
    }

    public function remedialExamSessions(): HasMany
    {
        return $this->hasMany(self::class, 'original_exam_session_id');
    }

    public function isRemedial(): bool
    {
        return $this->original_exam_session_id !== null;
    }

    public function isActive(): bool
    {
        return $this->status === ExamStatus::Active;
    }

    public function isWithinTimeWindow(): bool
    {
        $now = now();

        return $now->gte($this->starts_at) && $now->lte($this->ends_at);
    }
}
