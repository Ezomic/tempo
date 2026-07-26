<?php

declare(strict_types=1);

use App\Models\LifeEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

it('adds a life event and shows it on the wellness page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/life-events', [
        'date' => CarbonImmutable::now()->toDateString(),
        'kind' => 'travel',
        'note' => 'Flight to camp',
    ])->assertRedirect();

    expect($user->lifeEvents()->count())->toBe(1);

    $this->actingAs($user)->get('/wellness')
        ->assertInertia(fn ($page) => $page->has('lifeEvents', 1));
});

it('rejects an unknown kind', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/life-events', [
        'date' => CarbonImmutable::now()->toDateString(),
        'kind' => 'not_a_kind',
    ])->assertSessionHasErrors('kind');
});

it('lets only the owner delete an annotation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $event = LifeEvent::create([
        'user_id' => $owner->id,
        'date' => CarbonImmutable::now()->toDateString(),
        'kind' => 'illness',
    ]);

    $this->actingAs($other)->delete("/life-events/{$event->id}")->assertForbidden();
    expect(LifeEvent::find($event->id))->not->toBeNull();

    $this->actingAs($owner)->delete("/life-events/{$event->id}")->assertRedirect();
    expect(LifeEvent::find($event->id))->toBeNull();
});
