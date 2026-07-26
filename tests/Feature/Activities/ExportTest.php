<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

function exportActivity(User $user, array $extra = []): Activity
{
    return Activity::create(array_merge([
        'user_id' => $user->id,
        'external_id' => uniqid('exp_', true),
        'sport' => Sport::Run,
        'started_at' => CarbonImmutable::parse('2026-06-01 08:00:00'),
        'distance_m' => 10000,
        'duration_s' => 3000,
        'avg_hr' => 150,
        'trimp' => 80,
    ], $extra));
}

it('exports all activities as CSV with metrics', function () {
    $user = User::factory()->create();
    exportActivity($user, ['decoupling' => 4.5]);

    $response = $this->actingAs($user)->get('/export/activities.csv');
    $response->assertOk();

    $csv = $response->streamedContent();
    expect($csv)->toContain('date,sport,distance_m,duration_s,avg_hr,trimp,decoupling')
        ->and($csv)->toContain('2026-06-01,run,10000');
});

it('exports a single activity as CSV and TCX from its stream', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $activity = exportActivity($user, ['streams_path' => 'garmin/streams/x.json']);

    Storage::disk('local')->put('garmin/streams/x.json', json_encode([
        't' => [1717228800, 1717228801],
        'hr' => [140, 142],
        'speed' => [3.5, 3.6],
        'lat' => [52.0, 52.001],
        'lng' => [4.0, 4.001],
    ]));

    $csv = $this->actingAs($user)->get("/activities/{$activity->id}/export/csv");
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('timestamp,hr,speed_mps,lat,lng')
        ->and($csv->streamedContent())->toContain('1717228800,140,3.5');

    $tcx = $this->actingAs($user)->get("/activities/{$activity->id}/export/tcx");
    $tcx->assertOk();
    expect($tcx->streamedContent())->toContain('<TrainingCenterDatabase')
        ->and($tcx->streamedContent())->toContain('<Trackpoint>');
});

it('passes through the archived FIT file', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $activity = exportActivity($user, ['fit_path' => 'garmin/fit/x.fit']);
    Storage::disk('local')->put('garmin/fit/x.fit', 'RAWFITBYTES');

    $this->actingAs($user)->get("/activities/{$activity->id}/export/fit")
        ->assertOk()
        ->assertDownload('activity-'.$activity->id.'.fit');
});

it('does not let a non-owner export an activity', function () {
    $owner = User::factory()->create();
    $activity = exportActivity($owner);

    $this->actingAs(User::factory()->create())
        ->get("/activities/{$activity->id}/export/csv")
        ->assertForbidden();
});
