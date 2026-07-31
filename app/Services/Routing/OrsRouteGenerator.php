<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\DataObjects\GeneratedRoute;
use App\DataObjects\GeoPoint;
use App\Enums\Sport;
use App\Support\Payload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class OrsRouteGenerator implements RouteGenerator
{
    /** Bearings sampled when hunting for the flattest direction. */
    private const BEARINGS = [0, 60, 120, 180, 240, 300];

    public function __construct(
        private ?string $baseUrl,
        private ?string $key,
        private int $timeout = 20,
    ) {}

    public function isConfigured(): bool
    {
        return $this->baseUrl !== null && $this->baseUrl !== ''
            && $this->key !== null && $this->key !== '';
    }

    public function loop(GeoPoint $start, int $meters, Sport $sport, int $seed, bool $preferTrails = false): GeneratedRoute
    {
        $feature = $this->directions($sport, $preferTrails, [
            'coordinates' => [$start->toOrs()],
            'elevation' => true,
            'options' => [
                'round_trip' => [
                    'length' => $meters,
                    'points' => 4,
                    'seed' => $seed,
                ],
            ],
        ]);

        return GeneratedRoute::fromOrs(['features' => [$feature]], 'loop');
    }

    public function flatOutAndBack(GeoPoint $start, int $meters, Sport $sport, bool $preferTrails = false): GeneratedRoute
    {
        $legMeters = (int) round($meters / 2);

        $best = null;
        $bestScore = PHP_FLOAT_MAX;

        foreach (self::BEARINGS as $bearing) {
            $destination = $start->destination((float) $bearing, $legMeters);
            $feature = $this->directions($sport, $preferTrails, [
                'coordinates' => [$start->toOrs(), $destination->toOrs()],
                'elevation' => true,
            ]);

            $props = Payload::arr($feature, 'properties');
            $distance = Payload::float($props, 'summary', 'distance');
            $elevation = Payload::float($props, 'ascent') + Payload::float($props, 'descent');
            $score = $distance > 0 ? $elevation / ($distance / 1000) : PHP_FLOAT_MAX;

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $feature;
            }
        }

        return $this->outAndBackFrom($best ?? []);
    }

    /**
     * @param  array<string, mixed>  $leg  a single ORS GeoJSON feature (one-way leg)
     */
    private function outAndBackFrom(array $leg): GeneratedRoute
    {
        $props = $leg['properties'] ?? [];
        /** @var array<int, array<int, float>> $line */
        $line = $leg['geometry']['coordinates'] ?? [];

        $out = array_map(
            static fn (array $c): array => [(float) $c[1], (float) $c[0]],
            $line,
        );
        // Return the way we came, minus the duplicated turnaround point.
        $back = array_reverse(array_slice($out, 0, -1));

        $distance = Payload::toFloat($props['summary']['distance'] ?? 0);
        $ascent = Payload::toFloat($props['ascent'] ?? 0);
        $descent = Payload::toFloat($props['descent'] ?? 0);

        return new GeneratedRoute(
            coordinates: array_merge($out, $back),
            distanceM: $distance * 2,
            // Out-and-back climbs the leg's ascent and its descent on the return.
            ascentM: $ascent + $descent,
            kind: 'out_and_back',
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed> a single ORS GeoJSON feature
     */
    private function directions(Sport $sport, bool $preferTrails, array $body): array
    {
        $profile = $this->profile($sport, $preferTrails);

        $response = $this->request()
            ->post("/v2/directions/{$profile}/geojson", $body)
            ->throw();

        /** @var array<string, mixed> $feature */
        $feature = $response->json('features.0', []);

        return $feature;
    }

    /**
     * Bikes route as mountain biking (tracks/unpaved, away from traffic).
     * Running favours trails when asked, otherwise ordinary walking paths.
     */
    private function profile(Sport $sport, bool $preferTrails): string
    {
        return match ($sport) {
            Sport::Bike => 'cycling-mountain',
            default => $preferTrails ? 'foot-hiking' : 'foot-walking',
        };
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) $this->baseUrl)
            ->withHeaders([
                'Authorization' => (string) $this->key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/geo+json',
            ])
            ->timeout($this->timeout);
    }
}
