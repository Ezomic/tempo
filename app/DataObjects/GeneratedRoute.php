<?php

declare(strict_types=1);

namespace App\DataObjects;

use App\Support\Payload;

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
        $feature = Payload::arr($payload, 'features', 0);
        $summary = Payload::arr($feature, 'properties', 'summary');
        $line = Payload::arr($feature, 'geometry', 'coordinates');

        return new self(
            // ORS returns [lng, lat(, elev)]; store [lat, lng] for Leaflet.
            coordinates: array_values(array_map(
                static fn (mixed $c): array => [Payload::float($c, 1), Payload::float($c, 0)],
                $line,
            )),
            distanceM: Payload::float($summary, 'distance'),
            ascentM: Payload::float($feature, 'properties', 'ascent'),
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
