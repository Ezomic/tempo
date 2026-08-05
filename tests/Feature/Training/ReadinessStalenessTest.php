<?php

declare(strict_types=1);

use App\Enums\HrvStatus;
use App\Models\User;
use App\Models\WellnessDay;
use App\Services\Training\ReadinessService;
use Inertia\Testing\AssertableInertia as Assert;

function wellnessDaysAgo(User $user, int $days): WellnessDay
{
    return WellnessDay::create([
        'user_id' => $user->id,
        'date' => now()->subDays($days)->toDateString(),
        'sleep_score' => 90,
        'hrv_status' => HrvStatus::Balanced,
        'body_battery_high' => 95,
        'resting_hr' => 48,
    ]);
}

it('treats data from today as current', function () {
    $user = User::factory()->create();
    wellnessDaysAgo($user, 0);

    $snapshot = app(ReadinessService::class)->snapshot($user, null);

    expect($snapshot['age_days'])->toBe(0)
        ->and($snapshot['stale'])->toBeFalse()
        ->and($snapshot['summary'])->toContain('everything looks good');
});

it('still treats yesterday as current, since Garmin lands a day behind', function () {
    $user = User::factory()->create();
    wellnessDaysAgo($user, 1);

    expect(app(ReadinessService::class)->snapshot($user, null)['stale'])->toBeFalse();
});

it('marks data past the threshold as stale', function () {
    $user = User::factory()->create();
    wellnessDaysAgo($user, ReadinessService::STALE_AFTER_DAYS + 1);

    $snapshot = app(ReadinessService::class)->snapshot($user, null);

    expect($snapshot['stale'])->toBeTrue()
        ->and($snapshot['age_days'])->toBe(ReadinessService::STALE_AFTER_DAYS + 1);
});

it('drops the reassuring summary once the data is stale', function () {
    $user = User::factory()->create();
    wellnessDaysAgo($user, 6);

    $snapshot = app(ReadinessService::class)->snapshot($user, null);

    // The old wording claimed everything looked good for training off six-day-
    // old recovery data.
    expect($snapshot['summary'])->not->toContain('everything looks good')
        ->and($snapshot['summary'])->toContain('6 days old');
});

it('withholds a stale score from anything that acts on it', function () {
    $user = User::factory()->create();
    wellnessDaysAgo($user, 6);
    $service = app(ReadinessService::class);
    $stale = $service->snapshot($user, null);

    expect($service->actionableScore($stale))->toBeNull()
        // The score is still there to display, just not to act on.
        ->and($stale['score'])->toBeGreaterThan(0);
});

it('passes a fresh score through unchanged', function () {
    $user = User::factory()->create();
    wellnessDaysAgo($user, 0);
    $service = app(ReadinessService::class);
    $fresh = $service->snapshot($user, null);

    expect($service->actionableScore($fresh))->toBe($fresh['score']);
});

it('treats a missing snapshot as no score', function () {
    expect(app(ReadinessService::class)->actionableScore(null))->toBeNull();
});

it('sends the staleness flags to the dashboard', function () {
    $user = User::factory()->create();
    wellnessDaysAgo($user, 5);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('readiness.stale', true)
            ->where('readiness.age_days', 5));
});

it('reports fresh data as not stale on the dashboard', function () {
    $user = User::factory()->create();
    wellnessDaysAgo($user, 0);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('readiness.stale', false));
});
