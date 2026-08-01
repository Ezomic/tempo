<?php

declare(strict_types=1);

namespace App\Services\Weather;

use App\Models\PlannedWorkout;
use App\Models\User;
use App\Support\Payload;
use Carbon\CarbonImmutable;

class WeatherService
{
    public function __construct(private readonly WeatherForecaster $forecaster) {}

    /**
     * Forecast and heat/wind warning for an outdoor planned session within the
     * forecast horizon. Null when there is nothing to show (indoor, no known
     * location, or beyond the horizon).
     *
     * @return array{temp_max: float|null, wind_max: float|null, warning: bool, key: bool, reasons: list<string>}|null
     */
    public function forOutdoorSession(PlannedWorkout $workout, User $user, CarbonImmutable $today): ?array
    {
        if (! $workout->sport->isOutdoor()) {
            return null;
        }

        $location = $this->location($workout, $user);
        if ($location === null) {
            return null;
        }

        $date = $workout->date->startOfDay();
        $horizon = $today->startOfDay()->addDays($this->horizonDays());
        if ($date->lessThan($today->startOfDay()) || $date->greaterThan($horizon)) {
            return null;
        }

        $key = $workout->date->toDateString();
        $forecast = $this->forecaster->daily($location[0], $location[1], $key, $key)[$key] ?? null;
        if ($forecast === null) {
            return null;
        }

        $reasons = $this->reasons($forecast['temp_max'], $forecast['wind_max']);

        return [
            'temp_max' => $forecast['temp_max'],
            'wind_max' => $forecast['wind_max'],
            'warning' => $reasons !== [],
            'key' => $workout->workout_type?->isHard() ?? false,
            'reasons' => $reasons,
        ];
    }

    /**
     * @return list<string>
     */
    private function reasons(?float $tempMax, ?float $windMax): array
    {
        $reasons = [];

        if ($tempMax !== null && $tempMax >= $this->heatThreshold()) {
            $reasons[] = 'Heat '.round($tempMax).'°C';
        }
        if ($windMax !== null && $windMax >= $this->windThreshold()) {
            $reasons[] = 'Wind '.round($windMax).' km/h';
        }

        return $reasons;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function location(PlannedWorkout $workout, User $user): ?array
    {
        $geometry = $workout->route_geometry;
        if (is_array($geometry) && isset($geometry[0][0], $geometry[0][1])) {
            return [(float) $geometry[0][0], (float) $geometry[0][1]];
        }

        if ($user->home_lat !== null && $user->home_lng !== null) {
            return [(float) $user->home_lat, (float) $user->home_lng];
        }

        return null;
    }

    private function horizonDays(): int
    {
        return Payload::toInt(config('training.weather.horizon_days'));
    }

    private function heatThreshold(): float
    {
        return Payload::toFloat(config('training.weather.heat_c'));
    }

    private function windThreshold(): float
    {
        return Payload::toFloat(config('training.weather.wind_kmh'));
    }
}
