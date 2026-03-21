<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ForumThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'forum_category_id',
        'user_id',
        'title',
        'content',
        'is_pinned',
        'is_locked',
        'last_reply_at',
        'reply_count',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'last_reply_at' => 'datetime',
            'reply_count' => 'integer',
            'view_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class)->orderBy('created_at');
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(ForumReply::class)->latestOfMany();
    }

    public function scopePinnedFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('last_reply_at')->orderByDesc('created_at');
    }
}
