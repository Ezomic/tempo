<?php

declare(strict_types=1);

use App\Enums\Intensity;
use App\Enums\Sport;
use App\Models\User;
use App\Services\Training\WorkoutDescriber;

it('maps each intensity to a zone and hr band', function () {
    expect(Intensity::Recovery->zone())->toBe(1)
        ->and(Intensity::Hard->zone())->toBe(4)
        ->and(Intensity::Max->zone())->toBe(5)
        ->and(Intensity::Hard->hrPercent())->toBe('80-90%')
        ->and(Intensity::Easy->label())->toBe('Easy');

    expect(Intensity::options())->toHaveCount(5)
        ->and(Intensity::options()[0])->toHaveKeys(['value', 'label', 'zone', 'hr_percent', 'feel', 'color']);
});

it('computes a step total including recovery across repeats', function () {
    $user = User::factory()->create();
    $workout = $user->plannedWorkouts()->create([
        'date' => '2026-07-28',
        'sport' => Sport::Run,
        'title' => 'Reps',
    ]);

    $step = $workout->steps()->create([
        'position' => 0,
        'repeat' => 5,
        'intensity' => 'hard',
        'duration_min' => 3,
        'recovery_min' => 2,
    ]);

    // 5 * (3 + 2)
    expect($step->totalMinutes())->toBe(25);
});

it('describes a structured workout in plain language', function () {
    $user = User::factory()->create();
    $workout = $user->plannedWorkouts()->create([
        'date' => '2026-07-28',
        'sport' => Sport::Run,
        'title' => 'Threshold',
    ]);

    $workout->steps()->createMany([
        ['position' => 0, 'repeat' => 1, 'intensity' => 'easy', 'duration_min' => 15, 'label' => 'Warm up'],
        ['position' => 1, 'repeat' => 5, 'intensity' => 'hard', 'duration_min' => 3, 'recovery_min' => 2, 'recovery_intensity' => 'easy'],
    ]);

    $text = app(WorkoutDescriber::class)->describe($workout->load('steps'));

    expect($text)->toContain('Warm up: 15 min Easy (Z2, ~60-70% max HR).')
        ->toContain('5 x 3 min Hard (Z4, ~80-90% max HR) with 2 min Easy between.');
});

it('falls back to notes when a workout has no steps', function () {
    $user = User::factory()->create();
    $workout = $user->plannedWorkouts()->create([
        'date' => '2026-07-28',
        'sport' => Sport::Run,
        'title' => 'Easy',
        'notes' => 'Just cruise',
    ]);

    expect(app(WorkoutDescriber::class)->describe($workout->load('steps')))->toBe('Just cruise');
});
