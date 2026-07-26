<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use App\Services\Training\LoadGuardrailService;
use Carbon\CarbonImmutable;

function guardrailActivity(User $user, string $date, float $trimp): void
{
    Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('act_', true),
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::parse($date),
        'trimp' => $trimp,
    ]);
}

it('reports unknown with no history', function () {
    $user = User::factory()->create();

    $guardrails = app(LoadGuardrailService::class)
        ->guardrails($user, CarbonImmutable::parse('2026-07-15'));

    expect($guardrails['status'])->toBe('unknown')
        ->and($guardrails['acwr'])->toBeNull()
        ->and($guardrails['ramp_pct'])->toBeNull();
});

it('flags danger when acute load spikes over chronic', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');

    // A single big recent session with little chronic base spikes the ratio.
    guardrailActivity($user, '2026-07-15', 200);
    guardrailActivity($user, '2026-06-25', 20);

    $guardrails = app(LoadGuardrailService::class)->guardrails($user, $today);

    expect($guardrails['acwr_band'])->toBe('danger')
        ->and($guardrails['status'])->toBe('danger')
        ->and($guardrails['message'])->toContain('spiking');
});

it('stays safe when load is steady week to week', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');

    // Even 60 TRIMP every day for four weeks: acute ~= chronic, ramp ~= 0.
    for ($i = 0; $i < 28; $i++) {
        guardrailActivity($user, $today->subDays($i)->toDateString(), 60);
    }

    $guardrails = app(LoadGuardrailService::class)->guardrails($user, $today);

    expect($guardrails['status'])->toBe('safe')
        ->and($guardrails['ramp_pct'])->toBe(0.0);
});

it('flags a caution when the week-over-week ramp jumps', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-07-15');

    // Prior weeks steady to keep ACWR in range, then this week doubled.
    for ($i = 7; $i < 28; $i++) {
        guardrailActivity($user, $today->subDays($i)->toDateString(), 40);
    }
    for ($i = 0; $i < 7; $i++) {
        guardrailActivity($user, $today->subDays($i)->toDateString(), 80);
    }

    $guardrails = app(LoadGuardrailService::class)->guardrails($user, $today);

    expect($guardrails['ramp_pct'])->toBe(100.0)
        ->and($guardrails['ramp_band'])->toBe('danger');
});
