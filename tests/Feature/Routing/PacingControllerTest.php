<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;

const CONTROLLER_GPX = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><trkseg>
    <trkpt lat="52.0000" lon="4.0000"><ele>0</ele></trkpt>
    <trkpt lat="52.0050" lon="4.0000"><ele>20</ele></trkpt>
    <trkpt lat="52.0100" lon="4.0000"><ele>0</ele></trkpt>
  </trkseg></trk>
</gpx>
XML;

it('renders the pacing page', function () {
    $this->actingAs(User::factory()->create())->get('/pacing')->assertOk();
});

it('builds a pacing plan from an uploaded GPX', function () {
    $user = User::factory()->create();
    $gpx = UploadedFile::fake()->createWithContent('course.gpx', CONTROLLER_GPX);

    $this->actingAs($user)
        ->post('/pacing', [
            'gpx' => $gpx,
            'target_seconds' => 600,
            'split_km' => 1,
        ])
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('pacing/Index')
            ->has('plan.splits'));
});

it('rejects an invalid GPX file', function () {
    $user = User::factory()->create();
    $gpx = UploadedFile::fake()->createWithContent('bad.gpx', 'not really gpx');

    $this->actingAs($user)
        ->post('/pacing', [
            'gpx' => $gpx,
            'target_seconds' => 600,
            'split_km' => 1,
        ])
        ->assertSessionHasErrors('gpx');
});
