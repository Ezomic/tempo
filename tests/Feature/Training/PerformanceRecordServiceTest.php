<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use App\Services\Training\PerformanceRecordService;
use Carbon\CarbonImmutable;

/**
 * @param  array<int, int>  $bestEfforts
 * @param  array<int, float>  $meanMax
 */
function recordActivity(User $user, Sport $sport, string $date, array $bestEfforts, array $meanMax): Activity
{
    return Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('act_', true),
        'sport' => $sport,
        'started_at' => CarbonImmutable::parse($date),
        'best_efforts' => $bestEfforts,
        'mean_max' => $meanMax,
    ]);
}

it('keeps the best time per distance across activities', function () {
    $user = User::factory()->create();
    recordActivity($user, Sport::Run, '2026-07-01', [5000 => 1500], [300 => 4.0]);
    $faster = recordActivity($user, Sport::Run, '2026-07-10', [5000 => 1400], [300 => 4.5]);

    (new PerformanceRecordService)->recompute($user);

    $pr = $user->personalRecords()->where('distance_m', 5000)->first();
    expect($pr->duration_s)->toBe(1400)
        ->and($pr->activity_id)->toBe($faster->id);
});

it('keeps the fastest mean-max speed per sport and duration', function () {
    $user = User::factory()->create();
    recordActivity($user, Sport::Run, '2026-07-01', [], [300 => 4.0]);
    recordActivity($user, Sport::Run, '2026-07-10', [], [300 => 4.6]);
    recordActivity($user, Sport::Bike, '2026-07-10', [], [300 => 9.0]);

    (new PerformanceRecordService)->recompute($user);

    $run = $user->meanMaxEfforts()->where('sport', 'run')->where('duration_s', 300)->first();
    $bike = $user->meanMaxEfforts()->where('sport', 'bike')->where('duration_s', 300)->first();

    expect($run->speed_mps)->toBe(4.6)
        ->and($bike->speed_mps)->toBe(9.0);
});

it('rebuilds the envelope wholesale on recompute', function () {
    $user = User::factory()->create();
    recordActivity($user, Sport::Run, '2026-07-01', [1000 => 240], [60 => 4.2]);

    $service = new PerformanceRecordService;
    $service->recompute($user);
    expect($user->personalRecords()->count())->toBe(1);

    $service->recompute($user);
    expect($user->personalRecords()->count())->toBe(1);
});

it('renders the records page', function () {
    $user = User::factory()->create();
    recordActivity($user, Sport::Run, '2026-07-10', [5000 => 1400], [300 => 4.5]);
    (new PerformanceRecordService)->recompute($user);

    $this->actingAs($user)
        ->get('/records')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('records/Index')
            ->has('records', 1)
            ->has('meanMax', 1)
            ->where('records.0.distance_label', '5K')
            ->where('records.0.time', '23:20'));
});
