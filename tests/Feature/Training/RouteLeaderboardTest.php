<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use App\Services\Routing\RouteSignature;
use App\Services\Training\RouteLeaderboardService;
use Carbon\CarbonImmutable;

function routeActivity(User $user, string $routeKey, int $durationS, string $date): Activity
{
    return Activity::create([
        'user_id' => $user->id,
        'external_id' => uniqid('route_', true),
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::parse($date),
        'duration_s' => $durationS,
        'distance_m' => 10000,
        'route_key' => $routeKey,
    ]);
}

it('groups repeats of the same loop and matches slightly different distances', function () {
    $signature = new RouteSignature;
    $positions = [
        1 => [52.1000, 4.1000],
        2 => [52.1050, 4.1050],
        3 => [52.1000, 4.1000],
    ];

    // Same start/finish, distance 9.8 km vs 10.1 km -> same 500 m bucket.
    expect($signature->forPositions($positions, 9800.0))
        ->toBe($signature->forPositions($positions, 10100.0));

    // A different loop end -> different key.
    $other = $positions;
    $other[3] = [52.2000, 4.2000];
    expect($signature->forPositions($positions, 10000.0))
        ->not->toBe($signature->forPositions($other, 10000.0));
});

it('ranks efforts fastest first and flags the PB', function () {
    $user = User::factory()->create();
    routeActivity($user, 'loop-a', 2700, '2026-06-01'); // 45:00
    routeActivity($user, 'loop-a', 2550, '2026-06-08'); // 42:30 (best)
    routeActivity($user, 'loop-b', 1800, '2026-06-05'); // only one effort -> excluded

    $boards = app(RouteLeaderboardService::class)->boards($user);

    expect($boards)->toHaveCount(1)
        ->and($boards[0]['route_key'])->toBe('loop-a')
        ->and($boards[0]['efforts'][0]['duration_s'])->toBe(2550)
        ->and($boards[0]['efforts'][0]['is_best'])->toBeTrue()
        ->and($boards[0]['efforts'][1]['rank'])->toBe(2);
});

it('renders the leaderboard page', function () {
    $this->actingAs(User::factory()->create())->get('/leaderboard')->assertOk();
});
