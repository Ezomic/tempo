<?php

declare(strict_types=1);

use App\DataObjects\GeoPoint;
use App\Enums\Sport;
use App\Services\Routing\OrsRouteGenerator;
use Illuminate\Support\Facades\Http;

function ors(): OrsRouteGenerator
{
    return new OrsRouteGenerator('https://ors.test', 'test-key');
}

/**
 * @param  array<int, array<int, float>>  $coords  [lng, lat] pairs
 */
function orsFeature(array $coords, float $distance, float $ascent = 0, float $descent = 0): array
{
    return ['features' => [[
        'geometry' => ['coordinates' => $coords],
        'properties' => [
            'summary' => ['distance' => $distance],
            'ascent' => $ascent,
            'descent' => $descent,
        ],
    ]]];
}

it('reports configured only when a key is present', function () {
    expect(ors()->isConfigured())->toBeTrue()
        ->and((new OrsRouteGenerator('https://ors.test', null))->isConfigured())->toBeFalse();
});

it('builds a loop from an ORS round_trip and swaps to lat/lng', function () {
    Http::fake(['ors.test/*' => Http::response(
        orsFeature([[5.10, 52.00], [5.11, 52.01]], 10000.0), 200,
    )]);

    $route = ors()->loop(new GeoPoint(52.0, 5.1), 10000, Sport::Run, 42);

    expect($route->kind)->toBe('loop')
        ->and($route->distanceM)->toBe(10000.0)
        ->and($route->coordinates)->toBe([[52.0, 5.1], [52.01, 5.11]]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/directions/foot-walking/geojson')
        && $request['options']['round_trip']['length'] === 10000
        && $request['options']['round_trip']['seed'] === 42);
});

it('routes bikes as mountain biking', function () {
    Http::fake(['ors.test/*' => Http::response(orsFeature([[5.1, 52.0]], 5000.0), 200)]);

    ors()->loop(new GeoPoint(52.0, 5.1), 5000, Sport::Bike, 1);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/directions/cycling-mountain/geojson'));
});

it('uses the hiking profile when running prefers trails', function () {
    Http::fake(['ors.test/*' => Http::response(orsFeature([[5.1, 52.0]], 5000.0), 200)]);

    ors()->loop(new GeoPoint(52.0, 5.1), 5000, Sport::Run, 1, preferTrails: true);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/directions/foot-hiking/geojson'));
});

it('picks the flattest bearing for an out-and-back', function () {
    // Six bearings are sampled; the second one is dead flat.
    Http::fake(['ors.test/*' => Http::sequence()
        ->push(orsFeature([[5.1, 52.0]], 2000.0, ascent: 40, descent: 40), 200)
        ->push(orsFeature([[5.1, 52.0], [5.12, 52.0]], 2000.0, ascent: 0, descent: 0), 200)
        ->push(orsFeature([[5.1, 52.0]], 2000.0, ascent: 30, descent: 30), 200)
        ->push(orsFeature([[5.1, 52.0]], 2000.0, ascent: 55, descent: 55), 200)
        ->push(orsFeature([[5.1, 52.0]], 2000.0, ascent: 20, descent: 20), 200)
        ->push(orsFeature([[5.1, 52.0]], 2000.0, ascent: 60, descent: 60), 200),
    ]);

    $route = ors()->flatOutAndBack(new GeoPoint(52.0, 5.1), 4000, Sport::Run);

    expect($route->kind)->toBe('out_and_back')
        // Flattest leg had zero elevation change.
        ->and($route->ascentM)->toBe(0.0)
        // Out-and-back doubles the leg distance.
        ->and($route->distanceM)->toBe(4000.0);
});
