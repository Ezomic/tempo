<?php

declare(strict_types=1);

use App\Actions\GenerateTrainingPlanAction;
use App\Enums\Sport;
use App\Models\PlannedWorkout;
use App\Models\User;
use Carbon\CarbonImmutable;

it('spans from today to the race with sensible phases', function () {
    $start = CarbonImmutable::parse('2026-01-05'); // Monday
    $race = CarbonImmutable::parse('2026-04-05'); // ~13 weeks out

    $specs = (new GenerateTrainingPlanAction)->handle($start, $race, 4, 45.0);

    $phases = array_values(array_unique(array_map(fn (array $s): string => $s['phase'], $specs)));

    expect($specs)->not->toBeEmpty()
        ->and($phases)->toContain('base')
        ->and($phases)->toContain('build')
        ->and($phases)->toContain('peak')
        ->and($phases)->toContain('taper')
        // Last entry is race day.
        ->and($specs[array_key_last($specs)]['title'])->toBe('Race day')
        ->and($specs[array_key_last($specs)]['date'])->toBe('2026-04-05');
});

it('keeps the weekly load ramp within safe bounds', function () {
    $start = CarbonImmutable::parse('2026-01-05');
    $race = CarbonImmutable::parse('2026-04-05');

    $specs = (new GenerateTrainingPlanAction)->handle($start, $race, 4, 45.0);

    // Sum planned duration per ISO week, then check week-over-week growth.
    $weekly = [];
    foreach ($specs as $spec) {
        if ($spec['workout_type'] === null) {
            continue;
        }
        $week = CarbonImmutable::parse($spec['date'])->startOfWeek()->toDateString();
        $weekly[$week] = ($weekly[$week] ?? 0) + $spec['duration_min'];
    }

    $values = array_values($weekly);
    $hasDeload = false;
    for ($i = 1; $i < count($values); $i++) {
        if ($values[$i - 1] > 0) {
            $growth = $values[$i] / $values[$i - 1];
            // No runaway spike. The largest legitimate jump is bouncing back
            // from a deload week (~1.06 / 0.7), so allow up to ~1.55.
            expect($growth)->toBeLessThanOrEqual(1.55);
            if ($growth < 0.9) {
                $hasDeload = true;
            }
        }
    }

    // Periodization must include recovery/taper down-weeks.
    expect($hasDeload)->toBeTrue();
});

it('generates and persists a plan through the endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/plan/generate', [
            'race_date' => CarbonImmutable::now()->addWeeks(10)->toDateString(),
            'sport' => 'run',
            'sessions_per_week' => 4,
        ])
        ->assertRedirect(route('plan.index'));

    expect($user->plannedWorkouts()->whereNotNull('generated_at')->count())->toBeGreaterThan(10);
});

it('replaces generated sessions but keeps manual ones on regeneration', function () {
    $user = User::factory()->create();
    $manual = PlannedWorkout::create([
        'user_id' => $user->id,
        'date' => CarbonImmutable::now()->addWeek()->toDateString(),
        'sport' => Sport::Run,
        'title' => 'My own session',
    ]);

    $payload = [
        'race_date' => CarbonImmutable::now()->addWeeks(10)->toDateString(),
        'sport' => 'run',
        'sessions_per_week' => 4,
    ];

    $this->actingAs($user)->post('/plan/generate', $payload)->assertRedirect();
    $firstCount = $user->plannedWorkouts()->whereNotNull('generated_at')->count();

    $this->actingAs($user)->post('/plan/generate', $payload)->assertRedirect();
    $secondCount = $user->plannedWorkouts()->whereNotNull('generated_at')->count();

    expect($secondCount)->toBe($firstCount)
        ->and(PlannedWorkout::find($manual->id))->not->toBeNull();
});

it('rejects a race date in the past', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/plan/generate', [
            'race_date' => CarbonImmutable::now()->subDay()->toDateString(),
            'sport' => 'run',
            'sessions_per_week' => 4,
        ])
        ->assertSessionHasErrors('race_date');
});
