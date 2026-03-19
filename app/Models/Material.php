<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MaterialType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'subject_id',
        'classroom_id',
        'user_id',
        'title',
        'description',
        'type',
        'file_path',
        'file_original_name',
        'file_size',
        'video_url',
        'text_content',
        'topic',
        'order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'is_published' => 'boolean',
            'file_size' => 'integer',
            'order' => 'integer',
        ];
    }

    protected function formattedFileSize(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->file_size) {
                return null;
            }
            $units = ['B', 'KB', 'MB', 'GB'];
            $bytes = $this->file_size;
            $i = 0;
            while ($bytes >= 1024 && $i < count($units) - 1) {
                $bytes /= 1024;
                $i++;
            }

            return round($bytes, 1).' '.$units[$i];
        });
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

    public function progress(): HasMany
    {
        return $this->hasMany(MaterialProgress::class);
    }

    public function scopeForClassroom(Builder $query, int $classroomId): Builder
    {
        return $query->where('classroom_id', $classroomId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
