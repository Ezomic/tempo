<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use App\Services\Training\EfficiencyFactorService;
use Carbon\CarbonImmutable;

function efActivity(User $user, string $date, Sport $sport, ?float $ef): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('ef_', true),
        'sport' => $sport,
        'started_at' => CarbonImmutable::parse($date),
        'efficiency_factor' => $ef,
    ]);
}

it('averages efficiency factor per sport per week', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-15'); // a Monday

    efActivity($user, '2026-06-15', Sport::Run, 2.0);
    efActivity($user, '2026-06-17', Sport::Run, 2.2); // same week -> avg 2.1
    efActivity($user, '2026-06-16', Sport::Bike, 3.0);
    efActivity($user, '2026-06-16', Sport::Run, null); // no EF, ignored

    $trend = app(EfficiencyFactorService::class)->weeklyTrend($user, $today, 12);

    expect($trend)->toHaveKeys(['run', 'bike'])
        ->and($trend['run'][0]['ef'])->toBe(2.1)
        ->and($trend['bike'][0]['ef'])->toBe(3.0);
});

it('returns an empty trend when nothing is scored', function () {
    $user = User::factory()->create();

    expect(app(EfficiencyFactorService::class)->weeklyTrend($user, CarbonImmutable::now(), 12))
        ->toBe([]);
});
