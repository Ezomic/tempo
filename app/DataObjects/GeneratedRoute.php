<?php

declare(strict_types=1);

namespace App\DataObjects;

final readonly class GeneratedRoute
{
    /**
     * @param  array<int, array{0: float, 1: float}>  $coordinates  [lat, lng] points
     */
    public function __construct(
        public array $coordinates,
        public float $distanceM,
        public float $ascentM,
        public string $kind,
    ) {}

    /**
     * Build from an ORS GeoJSON directions response (one feature).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromOrs(array $payload, string $kind): self
    {
        $feature = $payload['features'][0] ?? [];
        $summary = $feature['properties']['summary'] ?? [];

        /** @var array<int, array{0: float, 1: float}> $line */
        $line = $feature['geometry']['coordinates'] ?? [];

        return new self(
            // ORS returns [lng, lat(, elev)]; store [lat, lng] for Leaflet.
            coordinates: array_map(
                static fn (array $c): array => [(float) $c[1], (float) $c[0]],
                $line,
            ),
            distanceM: (float) ($summary['distance'] ?? 0),
            ascentM: (float) ($feature['properties']['ascent'] ?? 0),
            kind: $kind,
        );
    }

    public function ascentPerKm(): float
    {
        $km = $this->distanceM / 1000;

        return $km > 0 ? $this->ascentM / $km : 0.0;
    }

    /**
     * @return array{coordinates: array<int, array{0: float, 1: float}>, distance_m: float, ascent_m: float, kind: string}
     */
    public function toArray(): array
    {
        return [
            'coordinates' => $this->coordinates,
            'distance_m' => round($this->distanceM),
            'ascent_m' => round($this->ascentM),
            'kind' => $this->kind,
        ];
    }
}
