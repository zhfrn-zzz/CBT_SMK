<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    use Auditable, HasFactory;

    protected array $auditExclude = ['is_flagged', 'answered_at'];

    protected $fillable = [
        'exam_attempt_id',
        'question_id',
        'answer',
        'is_flagged',
        'is_correct',
        'score',
        'feedback',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_flagged' => 'boolean',
            'is_correct' => 'boolean',
            'score' => 'decimal:2',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
