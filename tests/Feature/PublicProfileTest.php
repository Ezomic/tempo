<?php

declare(strict_types=1);

use App\Enums\Sport;
use App\Models\Activity;
use App\Models\PersonalRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

it('enabling produces a shareable link and disabling revokes it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/share')->assertRedirect();
    $token = $user->fresh()->public_profile_token;
    expect($token)->not->toBeNull();

    // The public page is reachable without auth.
    $this->get("/p/{$token}")->assertOk();

    $this->actingAs($user)->delete('/profile/share')->assertRedirect();
    expect($user->fresh()->public_profile_token)->toBeNull();

    // Revoked -> 404.
    $this->get("/p/{$token}")->assertNotFound();
});

it('exposes only whitelisted fields', function () {
    $user = User::factory()->create(['name' => 'Robbin']);
    $user->forceFill(['public_profile_token' => 'tok_test_123'])->save();

    $activity = Activity::create([
        'user_id' => $user->id, 'external_id' => 'pp-1', 'sport' => Sport::Run,
        'started_at' => CarbonImmutable::now()->subDay(),
    ]);
    PersonalRecord::create([
        'user_id' => $user->id, 'distance_m' => 5000, 'duration_s' => 1200,
        'activity_id' => $activity->id, 'achieved_on' => CarbonImmutable::now()->subDay()->toDateString(),
    ]);

    $this->get('/p/tok_test_123')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/Profile')
            ->where('profile.name', 'Robbin')
            ->has('profile.records', 1)
            ->has('profile.form')
            ->has('profile.sparkline')
            ->missing('profile.wellness')
            ->missing('profile.activities'));
});
