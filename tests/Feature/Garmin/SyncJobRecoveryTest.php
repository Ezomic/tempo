<?php

declare(strict_types=1);

use App\Jobs\SyncGarminJob;
use App\Models\GarminConnection;
use App\Models\User;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function syncingConnection(User $user, string $since): GarminConnection
{
    return GarminConnection::create([
        'user_id' => $user->id,
        'status' => GarminConnection::STATUS_CONNECTED,
        'sync_status' => GarminConnection::SYNC_SYNCING,
        'sync_status_since' => $since,
    ]);
}

it('marks the connection errored when the job is killed mid-run', function () {
    $user = User::factory()->create();
    $connection = syncingConnection($user, now()->toDateTimeString());

    (new SyncGarminJob($connection))->failed(null);

    expect($connection->fresh())
        ->sync_status->toBe(GarminConnection::SYNC_ERROR)
        ->sync_error->not->toBeNull();
});

it('keeps the failure reason when the job failed with an exception', function () {
    $user = User::factory()->create();
    $connection = syncingConnection($user, now()->toDateTimeString());

    (new SyncGarminJob($connection))->failed(new RuntimeException('Garmin sidecar is unreachable'));

    expect($connection->fresh()->sync_error)->toBe('Garmin sidecar is unreachable');
});

it('reports a long-running syncing row as stale', function () {
    $user = User::factory()->create();
    $connection = syncingConnection(
        $user,
        now()->subMinutes(GarminConnection::SYNC_STALE_AFTER_MINUTES + 1)->toDateTimeString(),
    );

    expect($connection->syncIsStale())->toBeTrue()
        ->and($connection->effectiveSyncStatus())->toBe(GarminConnection::SYNC_ERROR)
        ->and($connection->syncErrorMessage())->not->toBeNull();
});

it('leaves a sync that is still within its timeout alone', function () {
    $user = User::factory()->create();
    $connection = syncingConnection($user, now()->subMinute()->toDateTimeString());

    expect($connection->syncIsStale())->toBeFalse()
        ->and($connection->effectiveSyncStatus())->toBe(GarminConnection::SYNC_SYNCING)
        ->and($connection->syncErrorMessage())->toBeNull();
});

it('shows a stalled sync as an error on the settings page instead of a permanent spinner', function () {
    $user = User::factory()->create();
    syncingConnection(
        $user,
        now()->subMinutes(GarminConnection::SYNC_STALE_AFTER_MINUTES + 1)->toDateTimeString(),
    );

    $this->actingAs($user)
        ->get('/settings/garmin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Garmin')
            ->where('connection.sync_status', GarminConnection::SYNC_ERROR)
            ->whereNot('connection.sync_error', null));
});

it('does not queue a second sync while one is already running', function () {
    Queue::fake();
    $user = User::factory()->create();
    syncingConnection($user, now()->subMinute()->toDateTimeString());

    $this->actingAs($user)->post('/settings/garmin/sync')->assertRedirect();

    Queue::assertNothingPushed();
});

it('queues a sync again once the running one has gone stale', function () {
    Queue::fake();
    $user = User::factory()->create();
    syncingConnection(
        $user,
        now()->subMinutes(GarminConnection::SYNC_STALE_AFTER_MINUTES + 1)->toDateTimeString(),
    );

    $this->actingAs($user)->post('/settings/garmin/sync')->assertRedirect();

    Queue::assertPushed(SyncGarminJob::class);
});

it('guards the job against overlapping runs on the same connection', function () {
    $user = User::factory()->create();
    $connection = syncingConnection($user, now()->toDateTimeString());

    $middleware = (new SyncGarminJob($connection))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('allows the queue longer than the worker default so a first sync can finish', function () {
    $user = User::factory()->create();
    $job = new SyncGarminJob(syncingConnection($user, now()->toDateTimeString()));

    expect($job->timeout)->toBeGreaterThan(60)
        ->and((int) config('queue.connections.database.retry_after'))->toBeGreaterThan($job->timeout);
});
