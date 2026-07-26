<?php

declare(strict_types=1);

namespace App\Services\Weather;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class OpenMeteoForecaster implements WeatherForecaster
{
    public function __construct(private string $baseUrl) {}

    public function daily(float $lat, float $lng, string $from, string $to): array
    {
        // Only the coordinates and dates leave the app, which the forecast API
        // requires; nothing identifying is sent.
        $key = sprintf('weather:%.3f:%.3f:%s:%s', $lat, $lng, $from, $to);

        return Cache::remember($key, now()->addHours(3), function () use ($lat, $lng, $from, $to): array {
            try {
                $response = Http::baseUrl($this->baseUrl)
                    ->timeout(10)
                    ->get('/v1/forecast', [
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'daily' => 'temperature_2m_max,wind_speed_10m_max',
                        'wind_speed_unit' => 'kmh',
                        'timezone' => 'auto',
                        'start_date' => $from,
                        'end_date' => $to,
                    ])
                    ->throw();
            } catch (Throwable) {
                return [];
            }

            return $this->parse($response->json('daily'));
        });
    }

    /**
     * @return array<string, array{temp_max: float|null, wind_max: float|null}>
     */
    private function parse(mixed $daily): array
    {
        if (! is_array($daily) || ! is_array($daily['time'] ?? null)) {
            return [];
        }

        $temps = is_array($daily['temperature_2m_max'] ?? null) ? $daily['temperature_2m_max'] : [];
        $winds = is_array($daily['wind_speed_10m_max'] ?? null) ? $daily['wind_speed_10m_max'] : [];

        $out = [];
        foreach (array_values($daily['time']) as $i => $date) {
            if (! is_string($date)) {
                continue;
            }

            $out[$date] = [
                'temp_max' => is_numeric($temps[$i] ?? null) ? (float) $temps[$i] : null,
                'wind_max' => is_numeric($winds[$i] ?? null) ? (float) $winds[$i] : null,
            ];
        }

        return $out;
    }
}
