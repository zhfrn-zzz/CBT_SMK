<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeByGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    // ── Accessors ────────────────────────────────────────────────────

    public function getCastedValueAttribute(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json' => json_decode($this->value ?? '{}', true),
            default => $this->value,
        };
    }

    // ── Static helpers ───────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return app(\App\Services\SettingService::class)->get($key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        app(\App\Services\SettingService::class)->set($key, $value);
    }

    public static function getByGroup(string $group): array
    {
        return app(\App\Services\SettingService::class)->getByGroup($group);
    }
}
