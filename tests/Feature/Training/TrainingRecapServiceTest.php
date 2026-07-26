<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\DailyLoadMetric;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Services\Training\TrainingRecapService;
use Carbon\CarbonImmutable;

function recapActivity(User $user, string $date, Sport $sport, float $distanceM, int $seconds, float $elevation = 0): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('recap_', true),
        'sport' => $sport,
        'started_at' => CarbonImmutable::parse($date),
        'distance_m' => $distanceM,
        'moving_time_s' => $seconds,
        'elevation_gain_m' => $elevation,
    ]);
}

it('aggregates totals, PRs and CTL change over the range', function () {
    $user = User::factory()->create();
    $from = CarbonImmutable::parse('2026-06-01');
    $to = CarbonImmutable::parse('2026-06-30');

    recapActivity($user, '2026-06-05', Sport::Run, 10000, 3000, 120);
    recapActivity($user, '2026-06-12', Sport::Run, 8000, 2400, 40);
    recapActivity($user, '2026-06-20', Sport::Bike, 30000, 3600, 200);
    // Outside the range -> excluded.
    recapActivity($user, '2026-05-30', Sport::Run, 5000, 1500);

    $activity = $user->activities()->first();
    PersonalRecord::create([
        'user_id' => $user->id,
        'distance_m' => 5000,
        'duration_s' => 1200,
        'activity_id' => $activity->id,
        'achieved_on' => '2026-06-12',
    ]);

    DailyLoadMetric::create(['user_id' => $user->id, 'date' => '2026-06-01', 'trimp' => 0, 'ctl' => 40, 'atl' => 0, 'tsb' => 0]);
    DailyLoadMetric::create(['user_id' => $user->id, 'date' => '2026-06-30', 'trimp' => 0, 'ctl' => 52, 'atl' => 0, 'tsb' => 0]);

    $recap = app(TrainingRecapService::class)->recap($user, $from, $to);

    expect($recap['totals']['activities'])->toBe(3)
        ->and($recap['totals']['distance_m'])->toBe(48000.0)
        ->and($recap['totals']['hours'])->toBe(round(9000 / 3600, 1))
        ->and($recap['prs'])->toBe(1)
        ->and($recap['ctl_delta'])->toBe(12.0)
        ->and($recap['by_sport'])->toHaveKeys(['run', 'bike']);
});

it('renders the recap page', function () {
    $this->actingAs(User::factory()->create())->get('/recap')->assertOk();
});
