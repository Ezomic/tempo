<?php

declare(strict_types=1);

use App\Actions\GenerateTrainingPlanAction;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Chronos\ChronosClient;
use App\Services\Training\AutoRescheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

it('reads busy dates from chronos and degrades when unconfigured', function () {
    Http::fake([
        '*/free-busy*' => Http::response(['busy_dates' => ['2026-06-10', '2026-06-11']]),
    ]);

    $configured = new ChronosClient('https://chronos.test', 'token');
    expect($configured->busyDays('2026-06-01', '2026-06-30'))
        ->toBe(['2026-06-10', '2026-06-11']);

    $unconfigured = new ChronosClient(null, null);
    expect($unconfigured->busyDays('2026-06-01', '2026-06-30'))->toBe([]);
});

it('eases a key session that lands on a booked day in a generated plan', function () {
    $start = CarbonImmutable::parse('2026-01-05');
    $race = CarbonImmutable::parse('2026-04-05');
    $action = new GenerateTrainingPlanAction;

    $base = $action->handle($start, $race, 4, 45.0);
    $long = collect($base)->firstWhere('workout_type', 'long');
    expect($long)->not->toBeNull();

    $adjusted = $action->handle($start, $race, 4, 45.0, [$long['date']]);
    $onBusyDay = collect($adjusted)->firstWhere('date', $long['date']);

    expect($onBusyDay['workout_type'])->toBe('recovery');
});

it('avoids a booked day when rescheduling a missed session', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::parse('2026-06-17'); // Wednesday

    PlannedWorkout::create([
        'user_id' => $user->id, 'date' => '2026-06-15', 'sport' => Sport::Run,
        'workout_type' => WorkoutType::Intervals, 'title' => 'Intervals',
    ]);

    // Chronos reports today as booked.
    Http::fake(['*/free-busy*' => Http::response(['busy_dates' => ['2026-06-17']])]);
    $this->app->instance(ChronosClient::class, new ChronosClient('https://chronos.test', 'token'));

    $suggestion = app(AutoRescheduleService::class)
        ->suggestion($user, $today);

    // Wednesday is booked, so the next open day is Thursday.
    expect($suggestion['proposed_date'])->toBe('2026-06-18');
});
