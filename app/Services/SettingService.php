<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingService
{
    private const CACHE_KEY = 'settings:all';

    private const CACHE_TTL = 86400; // 24 hours

    public function getAll(): array
    {
        try {
            return Cache::store('redis')->remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return $this->loadFromDatabase();
            });
        } catch (\Exception) {
            // Fallback to database if Redis is down
            return $this->loadFromDatabase();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->getAll();

        if (! array_key_exists($key, $all)) {
            return $default;
        }

        return $all[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $setting = Setting::byKey($key)->first();

        if ($setting) {
            $convertedValue = $this->convertValueForStorage($value, $setting->type);
            $setting->update(['value' => $convertedValue]);
        }

        $this->clearCache();
    }

    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $setting = Setting::byKey($key)->first();

            if ($setting) {
                $convertedValue = $this->convertValueForStorage($value, $setting->type);
                $setting->update(['value' => $convertedValue]);
            }
        }

        $this->clearCache();
    }

    public function getByGroup(string $group): array
    {
        $all = $this->getAll();
        $settings = Setting::byGroup($group)->pluck('key')->toArray();

        return array_intersect_key($all, array_flip($settings));
    }

    public function clearCache(): void
    {
        try {
            Cache::store('redis')->forget(self::CACHE_KEY);
        } catch (\Exception) {
            // Redis might be down, cache will expire naturally
        }
    }

    public function handleFileUpload(string $key, UploadedFile $file): string
    {
        $setting = Setting::byKey($key)->first();

        // Delete old file if exists
        if ($setting && $setting->value) {
            $this->deleteFile($key);
        }

        $path = $file->store('settings', 'public');

        $this->set($key, $path);

        return $path;
    }

    public function deleteFile(string $key): void
    {
        $setting = Setting::byKey($key)->first();

        if ($setting && $setting->value && $setting->value !== 'images/logo.png') {
            Storage::disk('public')->delete($setting->value);
        }
    }

    private function loadFromDatabase(): array
    {
        $settings = Setting::all();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->casted_value;
        }

        return $result;
    }

    private function convertValueForStorage(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }
}
