<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read and change admin settings. A setting nobody has changed yet takes its
 * default from config/wiifarm.php ("settings" list), so every setting has a
 * known value from the first day.
 *
 *     Settings::get('verification.email_required')
 *     Settings::set('verification.email_required', true, adminId: 3)
 */
class Settings
{
    private const CACHE_KEY = 'wiifarm.settings';

    public static function get(string $key): mixed
    {
        $stored = self::all();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        // Setting names contain dots, so the defaults are read as one array.
        return config('wiifarm.settings')[$key] ?? null;
    }

    public static function set(string $key, mixed $value, ?int $adminId = null): void
    {
        if (! array_key_exists($key, config('wiifarm.settings'))) {
            throw new \InvalidArgumentException("Unknown setting [{$key}].");
        }

        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_by_admin_id' => $adminId, 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')
            ->map(fn ($v) => json_decode($v, true))
            ->all());
    }
}
