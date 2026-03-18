<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'subject_id',
        'user_id',
        'meeting_date',
        'meeting_number',
        'access_code',
        'code_expires_at',
        'is_open',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'code_expires_at' => 'datetime',
            'is_open' => 'boolean',
            'meeting_number' => 'integer',
        ];
    }

    protected function isCodeExpired(): Attribute
    {
        return Attribute::get(
            fn () => $this->code_expires_at && now()->gt($this->code_expires_at)
        );
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function scopeForClassroom(Builder $query, int $classroomId, int $subjectId): Builder
    {
        return $query->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId);
    }
}
