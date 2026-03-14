<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamActivityEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'exam_attempt_id',
        'event_type',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ExamActivityEventType::class,
            'created_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }
}
