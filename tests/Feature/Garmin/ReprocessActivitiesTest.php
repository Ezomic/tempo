<?php

declare(strict_types=1);

use App\Actions\ReprocessActivitiesAction;
use App\DataObjects\ParsedActivity;
use App\Enums\Sport;
use App\Models\Activity;
use App\Models\HrZoneSettings;
use App\Models\User;
use App\Services\Garmin\FitParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

function fakeParserWithHr(): FitParser
{
    return new class extends FitParser
    {
        public function parseData(string $bytes): ParsedActivity
        {
            $samples = [];
            for ($t = 0; $t <= 60; $t++) {
                $samples[1_700_000_000 + $t] = 150;
            }

            return new ParsedActivity($samples);
        }
    };
}

function archivedActivity(User $user, ?float $trimp = null): Activity
{
    $path = "garmin/fit/{$user->id}/act.fit";
    Storage::disk('local')->put($path, 'FAKE-FIT-BYTES');

    return Activity::create([
        'user_id' => $user->id,
        'external_id' => 'act',
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::parse('2026-07-20'),
        'trimp' => $trimp,
        'fit_path' => $path,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    $this->app->instance(FitParser::class, fakeParserWithHr());
});

it('recomputes trimp and streams from the archived fit file', function () {
    $user = User::factory()->create();
    HrZoneSettings::create(['user_id' => $user->id, 'max_hr' => 190, 'resting_hr' => 50, 'sex' => 'male']);
    $activity = archivedActivity($user, trimp: 1.0);

    $result = app(ReprocessActivitiesAction::class)->handle([$activity]);

    $activity->refresh();
    expect($result->updated)->toBe(1)
        ->and($result->failed())->toBe(0)
        ->and($activity->trimp)->toBeGreaterThan(1.0)
        ->and($activity->streams_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($activity->streams_path))->toBeTrue()
        // Recompute also rebuilds the fitness curve.
        ->and($user->dailyLoadMetrics()->count())->toBeGreaterThan(0);
});

it('skips activities with no archived fit file', function () {
    $user = User::factory()->create();
    $activity = Activity::create([
        'user_id' => $user->id,
        'external_id' => 'no-fit',
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::parse('2026-07-20'),
    ]);

    $result = app(ReprocessActivitiesAction::class)->handle([$activity]);

    expect($result->skipped)->toBe(1)
        ->and($result->updated)->toBe(0);
});

it('records a failure and keeps going when parsing throws', function () {
    $this->app->instance(FitParser::class, new class extends FitParser
    {
        public function parseData(string $bytes): ParsedActivity
        {
            throw new RuntimeException('corrupt fit');
        }
    });

    $user = User::factory()->create();
    $activity = archivedActivity($user);

    $result = app(ReprocessActivitiesAction::class)->handle([$activity]);

    expect($result->failed())->toBe(1)
        ->and($result->failures[0]['reason'])->toBe('corrupt fit')
        ->and($result->updated)->toBe(0);
});

it('requires a scope on the command', function () {
    $this->artisan('tempo:reprocess')
        ->expectsOutputToContain('Specify a scope')
        ->assertExitCode(1);
});

it('reprocesses a single activity by id via the command', function () {
    $user = User::factory()->create();
    HrZoneSettings::create(['user_id' => $user->id, 'max_hr' => 190, 'resting_hr' => 50, 'sex' => 'male']);
    $activity = archivedActivity($user, trimp: 1.0);

    $this->artisan("tempo:reprocess --activity={$activity->id}")
        ->assertExitCode(0);

    expect($activity->refresh()->trimp)->toBeGreaterThan(1.0);
});
