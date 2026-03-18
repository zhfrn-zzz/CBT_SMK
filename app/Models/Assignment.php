<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'classroom_id',
        'user_id',
        'title',
        'description',
        'file_path',
        'file_original_name',
        'deadline_at',
        'max_score',
        'allow_late_submission',
        'late_penalty_percent',
        'submission_type',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'submission_type' => SubmissionType::class,
            'deadline_at' => 'datetime',
            'max_score' => 'decimal:2',
            'allow_late_submission' => 'boolean',
            'late_penalty_percent' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn () => now()->gt($this->deadline_at));
    }

    protected function formattedDeadline(): Attribute
    {
        return Attribute::get(fn () => $this->deadline_at->translatedFormat('d M Y, H:i'));
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('deadline_at', '>', now());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('deadline_at', '<', now());
    }
}
