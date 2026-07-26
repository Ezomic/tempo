<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Training\AutoRescheduleService;
use Carbon\CarbonImmutable;

function plannedOn(User $user, string $date, WorkoutType $type): PlannedWorkout
{
    return PlannedWorkout::create([
        'user_id' => $user->id,
        'date' => $date,
        'sport' => Sport::Run,
        'workout_type' => $type,
        'title' => ucfirst($type->value),
    ]);
}

it('proposes a valid open day for a missed key session', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-17'); // Wednesday

    // Missed intervals on Monday, no activity logged.
    $missed = plannedOn($user, '2026-06-15', WorkoutType::Intervals);
    // An easy run already sits on Saturday.
    plannedOn($user, '2026-06-20', WorkoutType::Easy);

    $suggestion = app(AutoRescheduleService::class)->suggestion($user, $today);

    expect($suggestion)->not->toBeNull()
        ->and($suggestion['missed']['id'])->toBe($missed->id)
        // Earliest open day from today that is not next to a hard day.
        ->and($suggestion['proposed_date'])->toBe('2026-06-17');
});

it('keeps hard days apart when choosing the day', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-17'); // Wednesday

    plannedOn($user, '2026-06-15', WorkoutType::Intervals); // missed
    plannedOn($user, '2026-06-18', WorkoutType::Tempo); // hard on Thursday

    $suggestion = app(AutoRescheduleService::class)->suggestion($user, $today);

    // Wed (17) is adjacent to Thu hard, Thu is occupied, Fri (19) is adjacent
    // to Thu hard -> first valid is Saturday.
    expect($suggestion['proposed_date'])->toBe('2026-06-20');
});

it('returns nothing when no key session was missed', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-17');

    plannedOn($user, '2026-06-15', WorkoutType::Easy); // missed but not a key session

    expect(app(AutoRescheduleService::class)->suggestion($user, $today))->toBeNull();
});

it('moves the session only on apply', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-17');
    $missed = plannedOn($user, '2026-06-15', WorkoutType::Intervals);

    expect($missed->fresh()->date->toDateString())->toBe('2026-06-15');

    app(AutoRescheduleService::class)->apply($user, $today);

    expect($missed->fresh()->date->toDateString())->toBe('2026-06-17');
});
