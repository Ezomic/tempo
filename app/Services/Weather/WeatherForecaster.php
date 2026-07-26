<?php

declare(strict_types=1);

namespace App\Services\Weather;

interface WeatherForecaster
{
    /**
     * Daily forecast between two dates for a location, keyed by date.
     *
     * @return array<string, array{temp_max: float|null, wind_max: float|null}>
     */
    public function daily(float $lat, float $lng, string $from, string $to): array;
}
