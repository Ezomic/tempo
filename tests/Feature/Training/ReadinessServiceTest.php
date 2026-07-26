<?php

declare(strict_types=1);

use App\Enums\HrvStatus;
use App\Models\User;
use App\Models\WellnessDay;
use App\Services\Training\ReadinessService;

function wellness(User $user, array $attributes): void
{
    WellnessDay::create(array_merge([
        'user_id' => $user->id,
        'date' => '2026-07-15',
    ], $attributes));
}

it('is ready when HRV is balanced, battery healthy and load in range', function () {
    $user = User::factory()->create();
    wellness($user, ['hrv_status' => HrvStatus::Balanced, 'body_battery_high' => 80, 'resting_hr' => 48]);

    $snap = (new ReadinessService)->snapshot($user, 1.0);

    expect($snap['verdict'])->toBe('ready')
        ->and($snap['hrv_status'])->toBe('balanced')
        ->and($snap['body_battery'])->toBe(80);
});

it('advises rest when HRV is poor', function () {
    $user = User::factory()->create();
    wellness($user, ['hrv_status' => HrvStatus::Poor, 'body_battery_high' => 70]);

    expect((new ReadinessService)->snapshot($user, 1.0)['verdict'])->toBe('rest');
});

it('advises rest when the acute:chronic ratio spikes', function () {
    $user = User::factory()->create();
    wellness($user, ['hrv_status' => HrvStatus::Balanced, 'body_battery_high' => 90]);

    expect((new ReadinessService)->snapshot($user, 1.7)['verdict'])->toBe('rest');
});

it('cautions on a low body battery', function () {
    $user = User::factory()->create();
    wellness($user, ['hrv_status' => HrvStatus::Balanced, 'body_battery_high' => 15]);

    expect((new ReadinessService)->snapshot($user, 1.0)['verdict'])->toBe('caution');
});

it('returns null without any wellness data', function () {
    $user = User::factory()->create();

    expect((new ReadinessService)->snapshot($user, 1.0))->toBeNull();
});

it('breaks the score into contributors that reconcile with it', function () {
    $user = User::factory()->create();
    wellness($user, [
        'hrv_status' => HrvStatus::Low,
        'body_battery_high' => 40,
        'sleep_score' => 55,
    ]);

    $snap = (new ReadinessService)->snapshot($user, 1.7);

    // HRV low -30, battery <50 -10, sleep <65 -8, load >1.5 -25 => 100-73 = 27.
    expect($snap['score'])->toBe(27)
        ->and($snap['contributors'])->toHaveCount(4);

    $sum = array_sum(array_map(fn (array $c): int => $c['impact'], $snap['contributors']));
    expect(100 + $sum)->toBe($snap['score']);
});

it('summarises the two biggest drags on readiness', function () {
    $user = User::factory()->create();
    wellness($user, ['hrv_status' => HrvStatus::Poor, 'body_battery_high' => 20]);

    $snap = (new ReadinessService)->snapshot($user, 1.0);

    expect($snap['summary'])->toContain('HRV is poor')
        ->and($snap['summary'])->toContain('body battery is low');
});

it('gives an all-clear summary when nothing is wrong', function () {
    $user = User::factory()->create();
    wellness($user, ['hrv_status' => HrvStatus::Balanced, 'body_battery_high' => 90, 'sleep_score' => 88]);

    $snap = (new ReadinessService)->snapshot($user, 1.0);

    expect($snap['summary'])->toContain('everything looks good');
});
