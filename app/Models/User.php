<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isGuru(): bool
    {
        return $this->role === UserRole::Guru;
    }

    public function isSiswa(): bool
    {
        return $this->role === UserRole::Siswa;
    }

    public function dashboardRoute(): string
    {
        return match ($this->role) {
            UserRole::Admin => '/admin/dashboard',
            UserRole::Guru => '/guru/dashboard',
            UserRole::Siswa => '/siswa/dashboard',
        };
    }

    /**
     * Kelas yang diikuti siswa.
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_student')
            ->withTimestamps();
    }

    /**
     * Mata pelajaran yang diajar guru (via pivot classroom_subject_teacher).
     */
    public function teachingSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'classroom_subject_teacher')
            ->withPivot('classroom_id')
            ->withTimestamps();
    }

    /**
     * Kelas yang diajar guru (via pivot classroom_subject_teacher).
     */
    public function teachingClassrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_subject_teacher')
            ->withPivot('subject_id')
            ->withTimestamps();
    }

    /**
     * Penugasan mengajar guru (classroom + subject pairs).
     */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /**
     * Bank soal milik guru.
     */
    public function questionBanks(): HasMany
    {
        return $this->hasMany(QuestionBank::class);
    }

    /**
     * Sesi ujian milik guru.
     */
    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    /**
     * Attempt ujian milik siswa.
     */
    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * Cek apakah siswa punya attempt aktif.
     */
    public function activeExamAttempt(): ?ExamAttempt
    {
        return $this->examAttempts()
            ->where('status', \App\Enums\ExamAttemptStatus::InProgress)
            ->first();
    }
}
