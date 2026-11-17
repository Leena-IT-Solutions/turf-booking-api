<?php

namespace App\Models;

use App\Support\NotificationEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class NotificationSetting extends Model
{
    public const CACHE_KEY = 'notification_settings.matrix';

    protected $fillable = [
        'event_key',
        'push_enabled',
        'email_enabled',
        'config',
        'updated_by',
    ];

    protected $casts = [
        'push_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'config' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check whether a given notification event + channel ('push' | 'email') is enabled.
     * Uses cached matrix and falls back to legacy SaasSetting columns when no row exists yet.
     */
    public static function isEnabled(string $eventKey, string $channel = 'push'): bool
    {
        $definition = NotificationEvents::get($eventKey);
        if (! $definition) {
            return true;
        }

        if (! in_array($channel, $definition['channels'], true)) {
            return false;
        }

        $matrix = static::cachedMatrix();
        if (isset($matrix[$eventKey])) {
            $row = $matrix[$eventKey];
            if ($channel === 'email') {
                return (bool) ($row['email_enabled'] ?? true);
            }

            return (bool) ($row['push_enabled'] ?? true);
        }

        // Fallback to legacy SaasSetting boolean column if defined
        if ($channel === 'push' && ! empty($definition['legacy_saas_column'])) {
            $col = $definition['legacy_saas_column'];
            $saas = SaasSetting::cached();
            if ($saas && $saas->{$col} !== null) {
                return (bool) $saas->{$col};
            }
        }

        return true;
    }

    /**
     * Get a config value for an event (e.g. 'lead_minutes' for 'customer.match_reminder').
     */
    public static function configValue(string $eventKey, string $key, mixed $default = null): mixed
    {
        $definition = NotificationEvents::get($eventKey);
        $fallback = $definition['default_config'][$key] ?? $default;

        $matrix = static::cachedMatrix();
        if (isset($matrix[$eventKey]['config']) && is_array($matrix[$eventKey]['config']) && array_key_exists($key, $matrix[$eventKey]['config'])) {
            return $matrix[$eventKey]['config'][$key];
        }

        return $fallback;
    }

    /**
     * Ensure all registered events exist in DB and return keyed matrix.
     *
     * @return array<string, array{push_enabled: bool, email_enabled: ?bool, config: array<string, mixed>}>
     */
    public static function cachedMatrix(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                return static::loadMatrixFromDb();
            });
        } catch (\Throwable $e) {
            return static::loadMatrixFromDb();
        }
    }

    /**
     * @return array<string, array{push_enabled: bool, email_enabled: ?bool, config: array<string, mixed>}>
     */
    protected static function loadMatrixFromDb(): array
    {
        try {
            if (! Schema::hasTable('notification_settings')) {
                return [];
            }

            $rows = static::query()->get();
            $matrix = [];
            foreach ($rows as $row) {
                $matrix[$row->event_key] = [
                    'push_enabled' => (bool) $row->push_enabled,
                    'email_enabled' => $row->email_enabled !== null ? (bool) $row->email_enabled : null,
                    'config' => is_array($row->config) ? $row->config : [],
                ];
            }

            return $matrix;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function forgetCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // Ignore cache errors
        }
    }
}
