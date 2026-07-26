<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Training\TrainingLoadService;
use App\Services\Weather\WeatherService;
use Carbon\CarbonImmutable;

it('maps Garmin sport keys to the new sports', function () {
    expect(Sport::fromGarminTypeKey('lap_swimming'))->toBe(Sport::Swim)
        ->and(Sport::fromGarminTypeKey('strength_training'))->toBe(Sport::Strength)
        ->and(Sport::fromGarminTypeKey('hiking'))->toBe(Sport::Hike)
        ->and(Sport::fromGarminTypeKey('walking'))->toBe(Sport::Hike)
        ->and(Sport::fromGarminTypeKey('kayaking'))->toBe(Sport::Other);
});

it('knows which sports are outdoor', function () {
    expect(Sport::Hike->isOutdoor())->toBeTrue()
        ->and(Sport::Swim->isOutdoor())->toBeFalse()
        ->and(Sport::Strength->isOutdoor())->toBeFalse();
});

it('lets swim and strength activities contribute to load', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-15');

    Activity::create([
        'user_id' => $user->id, 'external_id' => 'swim-1', 'sport' => Sport::Swim,
        'started_at' => $today->subDays(2), 'trimp' => 60, 'duration_s' => 2400,
    ]);
    Activity::create([
        'user_id' => $user->id, 'external_id' => 'strength-1', 'sport' => Sport::Strength,
        'started_at' => $today->subDay(), 'trimp' => 40, 'duration_s' => 1800,
    ]);

    $service = app(TrainingLoadService::class);
    $chronic = $service->chronicBySport($user, $today);

    // Both land in the 'other' bucket and lift the total; no unhandled match.
    expect($chronic['other'])->toBeGreaterThan(0.0)
        ->and($chronic['total'])->toBeGreaterThan(0.0);
});

it('skips weather for an indoor sport', function () {
    $user = User::factory()->create(['home_lat' => 52.0, 'home_lng' => 4.0]);
    $swim = PlannedWorkout::create([
        'user_id' => $user->id,
        'date' => CarbonImmutable::now()->addDay()->toDateString(),
        'sport' => Sport::Swim,
        'title' => 'Pool session',
    ]);

    expect(app(WeatherService::class)->forOutdoorSession($swim, $user, CarbonImmutable::now()))
        ->toBeNull();
});
