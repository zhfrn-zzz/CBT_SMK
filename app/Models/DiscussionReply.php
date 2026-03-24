<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'discussion_thread_id',
        'user_id',
        'content',
    ];

    protected static function booted(): void
    {
        static::created(function (self $reply) {
            $thread = $reply->thread;
            \Illuminate\Support\Facades\DB::transaction(function () use ($reply, $thread) {
                $thread->increment('reply_count');
                $thread->update(['last_reply_at' => $reply->created_at]);
            });
        });

        static::deleted(function (self $reply) {
            $thread = $reply->thread;
            if ($thread) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($thread) {
                    $thread->decrement('reply_count');
                    $lastReply = $thread->replies()->latest()->first();
                    $thread->update(['last_reply_at' => $lastReply?->created_at]);
                });
            }
        });
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'discussion_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
