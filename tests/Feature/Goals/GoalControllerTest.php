<?php

declare(strict_types=1);

use App\Enums\GoalType;
use App\Models\TrainingGoal;
use App\Models\User;
use Carbon\CarbonImmutable;

it('renders the goals page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/goals')->assertOk();
});

it('creates a CTL goal', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/goals', [
        'type' => GoalType::Ctl->value,
        'target_value' => 60,
        'target_date' => CarbonImmutable::now()->addWeeks(8)->toDateString(),
    ])->assertRedirect();

    expect($user->trainingGoals()->count())->toBe(1);
});

it('requires a distance for a race-time goal', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/goals', [
        'type' => GoalType::RaceTime->value,
        'target_value' => 1200,
        'target_date' => CarbonImmutable::now()->addWeeks(8)->toDateString(),
    ])->assertSessionHasErrors('distance_m');
});

it('rejects a target date in the past', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/goals', [
        'type' => GoalType::Ctl->value,
        'target_value' => 60,
        'target_date' => CarbonImmutable::now()->subDay()->toDateString(),
    ])->assertSessionHasErrors('target_date');
});

it('deletes only the owner\'s goal', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $goal = TrainingGoal::create([
        'user_id' => $other->id,
        'type' => GoalType::Ctl,
        'target_value' => 60,
        'target_date' => CarbonImmutable::now()->addWeeks(8)->toDateString(),
    ]);

    $this->actingAs($user)->delete("/goals/{$goal->id}")->assertForbidden();
    expect(TrainingGoal::find($goal->id))->not->toBeNull();
});
