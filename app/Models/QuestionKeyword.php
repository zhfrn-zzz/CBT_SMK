<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'keyword',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
