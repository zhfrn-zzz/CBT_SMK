<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GradeLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'academic_year_id',
        'department_id',
        'grade_level',
    ];

    protected function casts(): array
    {
        return [
            'grade_level' => GradeLevel::class,
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_student')
            ->withTimestamps();
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_subject_teacher')
            ->withPivot('subject_id')
            ->withTimestamps();
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'classroom_subject_teacher')
            ->withPivot('user_id')
            ->withTimestamps();
    }
}
