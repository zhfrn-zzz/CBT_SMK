<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAnalysisCache extends Model
{
    use HasFactory;

    protected $table = 'item_analysis_cache';

    protected $fillable = ['exam_session_id', 'analysis_data', 'computed_at'];

    protected function casts(): array
    {
        return [
            'analysis_data' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }
}
