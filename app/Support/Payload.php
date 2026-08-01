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

    /**
     * A decoded stream file: named channels ("hr", "lat", ...) each holding a
     * list of samples. Anything that is not a named list is dropped.
     *
     * @return array<string, list<mixed>>
     */
    public static function streams(mixed $value, string|int ...$keys): array
    {
        $out = [];

        foreach (self::assoc($value, ...$keys) as $channel => $samples) {
            if (is_array($samples)) {
                $out[$channel] = array_values($samples);
            }
        }

        return $out;
    }

    /**
     * A decoded JSON object, keyed by name. Numeric keys are dropped so the
     * result is genuinely string-keyed rather than merely asserted to be.
     *
     * @return array<string, mixed>
     */
    public static function assoc(mixed $value, string|int ...$keys): array
    {
        $found = self::arr($value, ...$keys);
        $out = [];

        foreach ($found as $key => $item) {
            if (is_string($key)) {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    /**
     * Scalars are coerced rather than rejected: JSON identifiers arrive as
     * numbers as often as strings (Chronos event ids, Garmin workout ids),
     * and rejecting those silently yields '' instead of the id.
     */
    public static function str(mixed $value, string|int ...$keys): string
    {
        foreach ($keys as $key) {
            $value = is_array($value) ? ($value[$key] ?? null) : null;
        }

        return self::toStr($value);
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
