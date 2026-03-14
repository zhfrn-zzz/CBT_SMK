<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_bank_id',
        'type',
        'content',
        'media_path',
        'points',
        'explanation',
        'order',
        'metadata',
    ];

    protected $appends = ['media_url'];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'points' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected function mediaUrl(): Attribute
    {
        return Attribute::get(fn () => $this->media_path
            ? '/storage/'.$this->media_path
            : null
        );
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }
}
