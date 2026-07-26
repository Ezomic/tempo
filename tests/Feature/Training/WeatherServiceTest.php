<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Weather\OpenMeteoForecaster;
use App\Services\Weather\WeatherForecaster;
use App\Services\Weather\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, array{temp_max: float|null, wind_max: float|null}>  $days
 */
function fakeForecaster(array $days): WeatherForecaster
{
    return new class($days) implements WeatherForecaster
    {
        /** @param array<string, array{temp_max: float|null, wind_max: float|null}> $days */
        public function __construct(private array $days) {}

        public function daily(float $lat, float $lng, string $from, string $to): array
        {
            return $this->days;
        }
    };
}

function outdoorPlan(User $user, string $date, WorkoutType $type = WorkoutType::Easy): PlannedWorkout
{
    return PlannedWorkout::create([
        'user_id' => $user->id,
        'date' => $date,
        'sport' => Sport::Run,
        'workout_type' => $type,
        'title' => 'Run',
    ]);
}

it('returns the forecast for an upcoming outdoor session', function () {
    $user = User::factory()->create(['home_lat' => 52.0, 'home_lng' => 4.0]);
    $workout = outdoorPlan($user, '2026-07-21');

    $service = new WeatherService(fakeForecaster(['2026-07-21' => ['temp_max' => 18.0, 'wind_max' => 12.0]]));
    $weather = $service->forOutdoorSession($workout, $user, CarbonImmutable::parse('2026-07-20'));

    expect($weather)->not->toBeNull()
        ->and($weather['temp_max'])->toBe(18.0)
        ->and($weather['warning'])->toBeFalse();
});

it('warns on heat and wind for a key session', function () {
    $user = User::factory()->create(['home_lat' => 52.0, 'home_lng' => 4.0]);
    $workout = outdoorPlan($user, '2026-07-21', WorkoutType::Intervals);

    $service = new WeatherService(fakeForecaster(['2026-07-21' => ['temp_max' => 29.0, 'wind_max' => 35.0]]));
    $weather = $service->forOutdoorSession($workout, $user, CarbonImmutable::parse('2026-07-20'));

    expect($weather['warning'])->toBeTrue()
        ->and($weather['key'])->toBeTrue()
        ->and($weather['reasons'])->toContain('Heat 29°C')
        ->and($weather['reasons'])->toContain('Wind 35 km/h');
});

it('returns null beyond the forecast horizon', function () {
    $user = User::factory()->create(['home_lat' => 52.0, 'home_lng' => 4.0]);
    $workout = outdoorPlan($user, '2026-08-30');

    $service = new WeatherService(fakeForecaster(['2026-08-30' => ['temp_max' => 20.0, 'wind_max' => 10.0]]));

    expect($service->forOutdoorSession($workout, $user, CarbonImmutable::parse('2026-07-20')))->toBeNull();
});

it('returns null without a known location', function () {
    $user = User::factory()->create(['home_lat' => null, 'home_lng' => null]);
    $workout = outdoorPlan($user, '2026-07-21');

    $service = new WeatherService(fakeForecaster(['2026-07-21' => ['temp_max' => 20.0, 'wind_max' => 10.0]]));

    expect($service->forOutdoorSession($workout, $user, CarbonImmutable::parse('2026-07-20')))->toBeNull();
});

it('parses the open-meteo daily forecast and sends no personal data', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response([
            'daily' => [
                'time' => ['2026-07-21'],
                'temperature_2m_max' => [24.5],
                'wind_speed_10m_max' => [18.0],
            ],
        ], 200),
    ]);

    $daily = (new OpenMeteoForecaster('https://api.open-meteo.com'))
        ->daily(52.0, 4.0, '2026-07-21', '2026-07-21');

    expect($daily['2026-07-21']['temp_max'])->toBe(24.5)
        ->and($daily['2026-07-21']['wind_max'])->toBe(18.0);

    // Only coordinates and dates are sent, nothing identifying.
    Http::assertSent(function ($request): bool {
        $query = $request->data();

        return array_keys($query) === [
            'latitude', 'longitude', 'daily', 'wind_speed_unit', 'timezone', 'start_date', 'end_date',
        ];
    });
});
