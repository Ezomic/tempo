<?php

namespace App\Support;

/**
 * Typed readers for decoded JSON payloads from the external services
 * (Chronos, OpenRouteService, the Garmin sidecar), which hand back `mixed`
 * all the way down. Narrowing inline at every use site buries the logic,
 * so it lives here instead.
 */
final class Payload
{
    /** @return array<mixed> */
    public static function arr(mixed $value, string|int ...$keys): array
    {
        foreach ($keys as $key) {
            $value = is_array($value) ? ($value[$key] ?? null) : null;
        }

        return is_array($value) ? $value : [];
    }

    public static function str(mixed $value, string|int ...$keys): string
    {
        foreach ($keys as $key) {
            $value = is_array($value) ? ($value[$key] ?? null) : null;
        }

        return is_string($value) ? $value : '';
    }

    public static function nullableStr(mixed $value, string|int ...$keys): ?string
    {
        $found = self::str($value, ...$keys);

        return $found === '' ? null : $found;
    }

    public static function float(mixed $value, string|int ...$keys): float
    {
        foreach ($keys as $key) {
            $value = is_array($value) ? ($value[$key] ?? null) : null;
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function int(mixed $value, string|int ...$keys): int
    {
        foreach ($keys as $key) {
            $value = is_array($value) ? ($value[$key] ?? null) : null;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Narrow a already-extracted scalar (an Eloquent aggregate, a model
     * attribute) rather than walking a payload key path.
     */
    public static function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function toStr(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
