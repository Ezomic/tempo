<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\Activity;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Training\AdherenceService;
use Carbon\CarbonImmutable;

function planned(User $user, string $date, Sport $sport = Sport::Run, ?CarbonImmutable $adaptedAt = null): PlannedWorkout
{
    return PlannedWorkout::create([
        'user_id' => $user->id,
        'date' => $date,
        'sport' => $sport,
        'workout_type' => WorkoutType::Easy,
        'title' => 'Session',
        'adapted_at' => $adaptedAt,
    ]);
}

function did(User $user, string $date, Sport $sport = Sport::Run): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('act_', true),
        'sport' => $sport,
        'started_at' => CarbonImmutable::parse($date),
    ]);
}

function currentWeek(array $weeks): array
{
    return end($weeks);
}

it('marks a session completed when an activity lands on the same day', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15'); // Wed
    planned($user, '2026-07-15');
    did($user, '2026-07-15');

    $week = currentWeek((new AdherenceService)->weekly($user, $today, 4));

    expect($week['completed'])->toBe(1)
        ->and($week['skipped'])->toBe(0)
        ->and($week['adherence_pct'])->toBe(100);
});

it('counts an adapted session as modified', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');
    planned($user, '2026-07-15', adaptedAt: CarbonImmutable::parse('2026-07-15'));
    did($user, '2026-07-15');

    $week = currentWeek((new AdherenceService)->weekly($user, $today, 4));

    expect($week['modified'])->toBe(1)
        ->and($week['completed'])->toBe(0)
        ->and($week['adherence_pct'])->toBe(100);
});

it('recognises a moved session instead of a skip plus an extra', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-17'); // Fri
    planned($user, '2026-07-15'); // Wed
    did($user, '2026-07-16'); // done a day later

    $week = currentWeek((new AdherenceService)->weekly($user, $today, 4));

    expect($week['moved'])->toBe(1)
        ->and($week['skipped'])->toBe(0)
        ->and($week['adherence_pct'])->toBe(100);
});

it('marks a session skipped when nothing matches', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-17');
    planned($user, '2026-07-15');

    $week = currentWeek((new AdherenceService)->weekly($user, $today, 4));

    expect($week['skipped'])->toBe(1)
        ->and($week['adherence_pct'])->toBe(0)
        ->and($week['slipped'])->toHaveCount(1)
        ->and($week['slipped'][0]['title'])->toBe('Session');
});

it('does not reuse one activity for two planned sessions', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-16');
    planned($user, '2026-07-15');
    planned($user, '2026-07-16');
    did($user, '2026-07-15'); // only one activity for two plans

    $week = currentWeek((new AdherenceService)->weekly($user, $today, 4));

    // One resolves (completed/moved), the other is skipped.
    expect($week['skipped'])->toBe(1)
        ->and($week['completed'] + $week['moved'])->toBe(1);
});
