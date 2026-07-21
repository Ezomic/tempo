<?php

declare(strict_types=1);

use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;
use App\DataObjects\WellnessSnapshot;
use App\Jobs\SyncGarminJob;
use App\Models\GarminConnection;
use App\Models\HrZoneSettings;
use App\Models\User;
use App\Services\Garmin\GarminClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function stubClient(LoginResult $loginResult): GarminClient
{
    return new class($loginResult) implements GarminClient
    {
        public function __construct(private LoginResult $loginResult) {}

        public function login(GarminConnection $connection, string $email, string $password): LoginResult
        {
            return $this->loginResult;
        }

        public function resumeLoginWithMfa(GarminConnection $connection, string $loginToken, string $code): LoginResult
        {
            return new LoginResult('ok', displayName: 'Test Athlete');
        }

        public function status(GarminConnection $connection): ConnectionStatus
        {
            return new ConnectionStatus(true);
        }

        public function activities(GarminConnection $connection, CarbonImmutable $start, CarbonImmutable $end): array
        {
            return [];
        }

        public function downloadFit(GarminConnection $connection, string $activityId): string
        {
            return '';
        }

        public function wellness(GarminConnection $connection, CarbonImmutable $date): WellnessSnapshot
        {
            return WellnessSnapshot::fromSidecar(['date' => $date->toDateString()]);
        }
    };
}

it('renders the Garmin settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/garmin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Garmin')
            ->where('connection', null)
            ->where('settings.sex', 'male'));
});

it('connects a Garmin account without MFA', function () {
    $this->app->instance(GarminClient::class, stubClient(new LoginResult('ok', displayName: 'Test Athlete')));
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/settings/garmin/connect', ['email' => 'a@b.test', 'password' => 'secret'])
        ->assertRedirect();

    $connection = $user->garminConnection()->sole();
    expect($connection->status)->toBe(GarminConnection::STATUS_CONNECTED)
        ->and($connection->garmin_display_name)->toBe('Test Athlete');
});

it('flashes a login token when MFA is required', function () {
    $this->app->instance(GarminClient::class, stubClient(new LoginResult('mfa_required', loginToken: 'tok-123')));
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/settings/garmin/connect', ['email' => 'a@b.test', 'password' => 'secret'])
        ->assertSessionHas('garmin_login_token', 'tok-123');

    expect($user->garminConnection->status)->toBe(GarminConnection::STATUS_DISCONNECTED);
});

it('queues a sync for a connected account', function () {
    Queue::fake();
    $user = User::factory()->create();
    GarminConnection::create(['user_id' => $user->id, 'status' => GarminConnection::STATUS_CONNECTED]);

    $this->actingAs($user)->post('/settings/garmin/sync')->assertRedirect();

    Queue::assertPushed(SyncGarminJob::class);
});

it('rejects a sync when not connected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/settings/garmin/sync')->assertStatus(422);
});

it('saves heart-rate settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/garmin/hr-zones', ['max_hr' => 190, 'resting_hr' => 48, 'sex' => 'male'])
        ->assertRedirect();

    expect($user->hrZoneSettings()->sole())
        ->max_hr->toBe(190)
        ->resting_hr->toBe(48);
});

it('disconnects a Garmin account', function () {
    $user = User::factory()->create();
    GarminConnection::create(['user_id' => $user->id, 'status' => GarminConnection::STATUS_CONNECTED]);

    $this->actingAs($user)->delete('/settings/garmin')->assertRedirect();

    expect(HrZoneSettings::query()->count())->toBe(0)
        ->and($user->garminConnection()->exists())->toBeFalse();
});
